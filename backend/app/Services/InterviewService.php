<?php

namespace App\Services;

use App\Models\Application;
use App\Models\ApplicationStatusHistory;
use App\Models\Interview;
use App\Models\Notification;
use App\Models\User;
use App\Support\DomainStates;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\HttpException;

class InterviewService
{
    /**
     * INT-001 (API-INT-01): Tạo lịch phỏng vấn mới & đồng bộ đơn sang trạng thái 'interview'.
     */
    public function schedule(User $actor, int $applicationId, array $dto): Interview
    {
        return DB::transaction(function () use ($actor, $applicationId, $dto) {
            /** @var Application $application */
            $application = Application::with(['job.company', 'candidateProfile.user'])
                ->lockForUpdate()
                ->findOrFail($applicationId);

            $this->assertCompanyOwnership($actor, $application);

            // Chỉ cho phép lên lịch khi đơn đang ở 'reviewing' hoặc 'interview'
            if (!in_array($application->status, [
                DomainStates::APP_REVIEWING,
                DomainStates::APP_INTERVIEW,
            ], true)) {
                throw new HttpException(
                    409,
                    'Chỉ có thể tạo lịch phỏng vấn khi đơn ứng tuyển đang ở trạng thái đang xem xét hoặc phỏng vấn.'
                );
            }

            // Quyết định D-03: Tại một thời điểm tối đa 1 lịch 'scheduled' active
            $hasActiveSchedule = Interview::where('application_id', $application->id)
                ->where('status', DomainStates::INT_SCHEDULED)
                ->lockForUpdate()
                ->exists();

            if ($hasActiveSchedule) {
                throw new HttpException(
                    409,
                    'Đơn ứng tuyển này hiện đã có một lịch phỏng vấn đang chờ diễn ra.'
                );
            }

            $interview = new Interview([
                'application_id'     => $application->id,
                'scheduled_at'       => $dto['scheduled_at'],
                'location_or_mode'   => $dto['location_or_mode'],
                'notes'              => $dto['notes'] ?? null,
                'created_by_user_id' => $actor->id,
            ]);
            $interview->status = DomainStates::INT_SCHEDULED;
            $interview->save();

            // Nếu đơn đang 'reviewing', tự động chuyển sang 'interview' và ghi lịch sử
            if ($application->status === DomainStates::APP_REVIEWING) {
                $previousStatus = $application->status;
                $application->status = DomainStates::APP_INTERVIEW;
                $application->save();

                ApplicationStatusHistory::create([
                    'application_id'     => $application->id,
                    'from_status'        => $previousStatus,
                    'to_status'          => DomainStates::APP_INTERVIEW,
                    'changed_by_user_id' => $actor->id,
                    'note'               => 'Tự động chuyển sang phỏng vấn khi tạo lịch hẹn.',
                    'changed_at'         => now(),
                ]);
            }

            $this->createCandidateNotification(
                $application,
                $interview,
                'interview.created',
                sprintf(
                    'Bạn có lịch phỏng vấn mới cho vị trí "%s" vào lúc %s (%s).',
                    $application->job->title,
                    $interview->scheduled_at->format('H:i d/m/Y'),
                    $interview->location_or_mode
                )
            );

            return $interview->load(['application', 'createdBy']);
        });
    }

    /**
     * INT-002 (API-INT-02): Cập nhật lịch phỏng vấn đang ở trạng thái 'scheduled'.
     */
    public function update(User $actor, int $id, array $dto): Interview
    {
        return DB::transaction(function () use ($actor, $id, $dto) {
            /** @var Interview $interview */
            $interview = Interview::with(['application.job.company', 'application.candidateProfile.user'])
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCompanyOwnership($actor, $interview->application);

            if ($interview->status !== DomainStates::INT_SCHEDULED) {
                throw new HttpException(
                    409,
                    'Chỉ có thể chỉnh sửa lịch phỏng vấn đang ở trạng thái đã lên lịch (scheduled).'
                );
            }

            $interview->fill([
                'scheduled_at'     => $dto['scheduled_at'],
                'location_or_mode' => $dto['location_or_mode'],
                'notes'            => array_key_exists('notes', $dto) ? $dto['notes'] : $interview->notes,
            ]);
            $interview->save();

            $this->createCandidateNotification(
                $interview->application,
                $interview,
                'interview.updated',
                sprintf(
                    'Lịch phỏng vấn vị trí "%s" đã được cập nhật sang %s (%s).',
                    $interview->application->job->title,
                    $interview->scheduled_at->format('H:i d/m/Y'),
                    $interview->location_or_mode
                )
            );

            return $interview->load(['application', 'createdBy']);
        });
    }

    /**
     * INT-003 (API-INT-03): Hủy lịch phỏng vấn & đưa đơn về 'reviewing' nếu không còn lịch thay thế.
     */
    public function cancel(User $actor, int $id, ?string $reason = null): Interview
    {
        return DB::transaction(function () use ($actor, $id, $reason) {
            /** @var Interview $interview */
            $interview = Interview::lockForUpdate()->findOrFail($id);

            /** @var Application $application */
            $application = Application::with(['job.company', 'candidateProfile.user'])
                ->lockForUpdate()
                ->findOrFail($interview->application_id);

            $this->assertCompanyOwnership($actor, $application);

            if ($interview->status !== DomainStates::INT_SCHEDULED) {
                throw new HttpException(
                    409,
                    'Chỉ có thể hủy lịch phỏng vấn đang ở trạng thái đã lên lịch (scheduled).'
                );
            }

            $interview->status = DomainStates::INT_CANCELLED;
            $interview->cancel_reason = $reason;
            $interview->save();

            // Kiểm tra xem đơn ứng tuyển còn bất kỳ lịch 'scheduled' nào khác không
            $remainingScheduledCount = Interview::where('application_id', $application->id)
                ->where('status', DomainStates::INT_SCHEDULED)
                ->count();

            if ($remainingScheduledCount === 0 && $application->status === DomainStates::APP_INTERVIEW) {
                $application->status = DomainStates::APP_REVIEWING;
                $application->save();

                ApplicationStatusHistory::create([
                    'application_id'     => $application->id,
                    'from_status'        => DomainStates::APP_INTERVIEW,
                    'to_status'          => DomainStates::APP_REVIEWING,
                    'changed_by_user_id' => $actor->id,
                    'note'               => $reason
                        ? "Hủy lịch phỏng vấn cuối cùng: {$reason}"
                        : 'Tự động đưa đơn về đang xem xét do hủy lịch phỏng vấn cuối cùng.',
                    'changed_at'         => now(),
                ]);
            }

            $this->createCandidateNotification(
                $application,
                $interview,
                'interview.cancelled',
                sprintf(
                    'Lịch phỏng vấn vị trí "%s" vào lúc %s đã bị hủy.%s',
                    $application->job->title,
                    $interview->scheduled_at->format('H:i d/m/Y'),
                    $reason ? " Lý do: {$reason}" : ''
                )
            );

            $interview->setRelation('application', $application);

            return $interview->load('createdBy');
        });
    }

    /**
     * INT-004 (API-INT-04): Đánh dấu hoàn thành buổi phỏng vấn (giữ nguyên trạng thái đơn).
     */
    public function complete(User $actor, int $id, ?string $note = null): Interview
    {
        return DB::transaction(function () use ($actor, $id, $note) {
            /** @var Interview $interview */
            $interview = Interview::with(['application.job.company'])
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCompanyOwnership($actor, $interview->application);

            if ($interview->status !== DomainStates::INT_SCHEDULED) {
                throw new HttpException(
                    409,
                    'Chỉ có thể đánh dấu hoàn thành đối với lịch phỏng vấn đang ở trạng thái scheduled.'
                );
            }

            $interview->status = DomainStates::INT_COMPLETED;
            if ($note !== null && $note !== '') {
                $interview->notes = $note;
            }
            $interview->save();

            return $interview->load(['application', 'createdBy']);
        });
    }

    /**
     * INT-005 (API-INT-05): Ghi nhận ứng viên vắng mặt (giữ nguyên trạng thái đơn).
     */
    public function noShow(User $actor, int $id, ?string $note = null): Interview
    {
        return DB::transaction(function () use ($actor, $id, $note) {
            /** @var Interview $interview */
            $interview = Interview::with(['application.job.company'])
                ->lockForUpdate()
                ->findOrFail($id);

            $this->assertCompanyOwnership($actor, $interview->application);

            if ($interview->status !== DomainStates::INT_SCHEDULED) {
                throw new HttpException(
                    409,
                    'Chỉ có thể ghi nhận vắng mặt đối với lịch phỏng vấn đang ở trạng thái scheduled.'
                );
            }

            $interview->status = DomainStates::INT_NO_SHOW;
            if ($note !== null && $note !== '') {
                $interview->notes = $note;
            }
            $interview->save();

            return $interview->load(['application', 'createdBy']);
        });
    }

    /**
     * Kiểm tra phòng thủ kép tại tầng Service: Doanh nghiệp đã verified và sở hữu job.
     */
    private function assertCompanyOwnership(User $actor, Application $application): void
    {
        $company = $actor->company;

        if (
            $actor->role !== 'company'
            || $company === null
            || $company->verification_status !== 'verified'
            || $application->job === null
            || (int) $application->job->company_id !== (int) $company->id
        ) {
            throw new AuthorizationException('Bạn không có quyền thao tác lịch phỏng vấn trên đơn ứng tuyển này.');
        }
    }

    /**
     * Ghi bản ghi notification website cho ứng viên trong cùng DB transaction.
     */
    private function createCandidateNotification(
        Application $application,
        Interview $interview,
        string $eventType,
        string $content
    ): void {
        $candidateUserId = $application->candidateProfile?->user_id;
        if (!$candidateUserId) {
            return;
        }

        Notification::create([
            'user_id'      => $candidateUserId,
            'event_type'   => $eventType,
            'content'      => $content,
            'related_type' => 'interview',
            'related_id'   => $interview->id,
            'created_at'   => now(),
        ]);
    }
}
