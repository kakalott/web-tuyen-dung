<?php

namespace App\Services;

use App\Models\CandidateProfile;
use App\Models\Education;
use App\Models\Experience;
use App\Models\User;
use App\Support\ApiError;
use Illuminate\Support\Facades\DB;

class CandidateProfileService
{
    /**
     * Lấy hồ sơ ứng viên hiện hành từ User (không nhận ID từ client).
     */
    protected function resolveProfile(User $actor): CandidateProfile
    {
        $profile = CandidateProfile::where('user_id', $actor->id)->first();

        if (! $profile) {
            throw ApiError::notFound('Hồ sơ ứng viên không tồn tại.');
        }

        return $profile;
    }

    /**
     * API-CAN-01: Lấy thông tin hồ sơ kèm học vấn và kinh nghiệm sắp xếp start_date DESC, id DESC.
     */
    public function show(User $actor): array
    {
        $profile = $this->resolveProfile($actor);

        $educations = Education::where('candidate_profile_id', $profile->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        $experiences = Experience::where('candidate_profile_id', $profile->id)
            ->orderByDesc('start_date')
            ->orderByDesc('id')
            ->get();

        return [
            'profile'     => $profile,
            'educations'  => $educations,
            'experiences' => $experiences,
        ];
    }

    /**
     * API-CAN-02: Cập nhật thông tin hồ sơ (whitelist summary, phone, location).
     */
    public function update(User $actor, array $dto): CandidateProfile
    {
        return DB::transaction(function () use ($actor, $dto) {
            $profile = CandidateProfile::where('user_id', $actor->id)
                ->lockForUpdate()
                ->first();

            if (! $profile) {
                throw ApiError::notFound('Hồ sơ ứng viên không tồn tại.');
            }

            $allowed = array_intersect_key($dto, array_flip(['summary', 'phone', 'location']));
            $profile->fill($allowed);
            $profile->save();

            return $profile->fresh();
        });
    }

    /**
     * API-CAN-03 & API-CAN-04: Thêm mới hoặc cập nhật bản ghi Học vấn.
     */
    public function saveEducation(User $actor, array $dto, ?int $id = null): Education
    {
        $profile = $this->resolveProfile($actor);

        return DB::transaction(function () use ($profile, $dto, $id) {
            if ($id === null) {
                $education = new Education();
                $education->candidate_profile_id = $profile->id;
            } else {
                $education = Education::where('id', $id)->lockForUpdate()->first();

                if (! $education) {
                    throw ApiError::notFound('Không tìm thấy bản ghi học vấn.');
                }

                if ((int) $education->candidate_profile_id !== (int) $profile->id) {
                    throw ApiError::forbidden('Bạn không có quyền thao tác trên bản ghi học vấn này.');
                }
            }

            $allowed = array_intersect_key($dto, array_flip([
                'institution',
                'degree',
                'field_of_study',
                'start_date',
                'end_date',
                'description',
            ]));

            $education->fill($allowed);
            $education->save();

            return $education->fresh();
        });
    }

    /**
     * API-CAN-05: Xóa bản ghi Học vấn thuộc hồ sơ hiện tại.
     */
    public function deleteEducation(User $actor, int $id): void
    {
        $profile = $this->resolveProfile($actor);

        DB::transaction(function () use ($profile, $id) {
            $education = Education::where('id', $id)->lockForUpdate()->first();

            if (! $education) {
                throw ApiError::notFound('Không tìm thấy bản ghi học vấn.');
            }

            if ((int) $education->candidate_profile_id !== (int) $profile->id) {
                throw ApiError::forbidden('Bạn không có quyền xóa bản ghi học vấn này.');
            }

            $education->delete();
        });
    }

    /**
     * API-CAN-06 & API-CAN-07: Thêm mới hoặc cập nhật bản ghi Kinh nghiệm làm việc.
     */
    public function saveExperience(User $actor, array $dto, ?int $id = null): Experience
    {
        $profile = $this->resolveProfile($actor);

        return DB::transaction(function () use ($profile, $dto, $id) {
            if ($id === null) {
                $experience = new Experience();
                $experience->candidate_profile_id = $profile->id;
            } else {
                $experience = Experience::where('id', $id)->lockForUpdate()->first();

                if (! $experience) {
                    throw ApiError::notFound('Không tìm thấy bản ghi kinh nghiệm.');
                }

                if ((int) $experience->candidate_profile_id !== (int) $profile->id) {
                    throw ApiError::forbidden('Bạn không có quyền thao tác trên bản ghi kinh nghiệm này.');
                }
            }

            $allowed = array_intersect_key($dto, array_flip([
                'employer_name',
                'job_title',
                'start_date',
                'end_date',
                'description',
            ]));

            $experience->fill($allowed);
            $experience->save();

            return $experience->fresh();
        });
    }

    /**
     * API-CAN-08: Xóa bản ghi Kinh nghiệm thuộc hồ sơ hiện tại.
     */
    public function deleteExperience(User $actor, int $id): void
    {
        $profile = $this->resolveProfile($actor);

        DB::transaction(function () use ($profile, $id) {
            $experience = Experience::where('id', $id)->lockForUpdate()->first();

            if (! $experience) {
                throw ApiError::notFound('Không tìm thấy bản ghi kinh nghiệm.');
            }

            if ((int) $experience->candidate_profile_id !== (int) $profile->id) {
                throw ApiError::forbidden('Bạn không có quyền xóa bản ghi kinh nghiệm này.');
            }

            $experience->delete();
        });
    }
}
