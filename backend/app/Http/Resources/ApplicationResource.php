<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ApplicationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isCompanyOrAdmin = in_array($request->user()?->role, ['company', 'admin'], true);

        return [
            'id'                   => $this->id,
            'candidate_profile_id' => $this->candidate_profile_id,
            'job_id'               => $this->job_id,
            'cv_id'                => $this->cv_id,
            'cv_filename'          => $this->relationLoaded('cv') ? $this->cv?->original_filename : null,
            'status'               => $this->status,
            'applied_at'           => $this->applied_at?->toIso8601String(),
            'withdrawn_at'         => $this->withdrawn_at?->toIso8601String(),

            // Thông tin tin tuyển dụng & công ty (khi load job)
            'job' => $this->whenLoaded('job', fn () => [
                'id'           => $this->job->id,
                'title'        => $this->job->title,
                'company_id'   => $this->job->company_id,
                'company_name' => $this->job->relationLoaded('company') ? $this->job->company?->company_name : null,
            ]),

            // Thông tin ứng viên (chỉ trả khi load candidateProfile cho doanh nghiệp)
            'candidate' => $this->whenLoaded('candidateProfile', fn () => [
                'id'          => $this->candidateProfile->id,
                'user_id'     => $this->candidateProfile->user_id,
                'name'        => $this->candidateProfile->relationLoaded('user') ? $this->candidateProfile->user?->name : null,
                'email'       => $this->candidateProfile->relationLoaded('user') ? $this->candidateProfile->user?->email : null,
                'phone'       => $this->candidateProfile->phone,
                'location'    => $this->candidateProfile->location,
                'summary'     => $this->candidateProfile->summary,
                'educations'  => $this->when($this->candidateProfile->relationLoaded('educations'), fn () => $this->candidateProfile->educations),
                'experiences' => $this->when($this->candidateProfile->relationLoaded('experiences'), fn () => $this->candidateProfile->experiences),
                'skills'      => $this->when($this->candidateProfile->relationLoaded('skills'), fn () => $this->candidateProfile->skills->map(fn ($s) => [
                    'id'   => $s->id,
                    'name' => $s->name,
                ])->values()),
            ]),

            // Metadata CV Snapshot (TUYỆT ĐỐI KHÔNG trả về storage_key)
            'cv' => $this->whenLoaded('cv', fn () => $this->cv ? [
                'id'                => $this->cv->id,
                'original_filename' => $this->cv->original_filename,
                'parse_status'      => $this->cv->parse_status,
                'uploaded_at'       => $this->cv->uploaded_at?->toIso8601String(),
                'extraction'        => $this->when(
                    $this->cv->relationLoaded('latestExtraction') && $this->cv->latestExtraction,
                    fn () => [
                        'id'            => $this->cv->latestExtraction->id,
                        'review_status' => $this->cv->latestExtraction->review_status,
                        'skills'        => $this->when(
                            $this->cv->latestExtraction->relationLoaded('skills'),
                            fn () => $this->cv->latestExtraction->skills->map(fn ($s) => [
                                'id'   => $s->id,
                                'name' => $s->name,
                            ])->values()
                        ),
                    ]
                ),
            ] : null),

            // Lịch sử chuyển trạng thái (ẩn ghi chú nội bộ của DN đối với Candidate - APP-002)
            'history' => $this->whenLoaded('statusHistories', fn () => $this->statusHistories->map(fn ($h) => [
                'id'          => $h->id,
                'from_status' => $h->from_status,
                'to_status'   => $h->to_status,
                'note'        => $isCompanyOrAdmin ? $h->note : null,
                'changed_at'  => $h->changed_at?->toIso8601String(),
            ])->values()),

            // Lịch phỏng vấn đính kèm
            'interviews' => InterviewResource::collection($this->whenLoaded('interviews')),

            // Kết quả AI Matching tham khảo (nếu đã có)
            'match_result' => $this->whenLoaded('latestMatchResult', fn () => $this->latestMatchResult
                ? (new MatchResultResource($this->latestMatchResult))->resolve($request)
                : null
            ),
        ];
    }
}
