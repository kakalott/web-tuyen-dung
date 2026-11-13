<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class JobResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $user = $request->user();
        $isAdmin = $user !== null && $user->role === 'admin';
        $isOwnerCompany = $user !== null
            && $user->role === 'company'
            && $user->company !== null
            && (int) $user->company->id === (int) $this->company_id;

        $canSeeModerationFields = $isAdmin || $isOwnerCompany;

        return [
            'id'                 => $this->id,
            'company_id'         => $this->company_id,
            'title'              => $this->title,
            'description'        => $this->description,
            'requirements_text'  => $this->requirements_text,
            'job_group_id'       => $this->job_group_id,
            'employment_type_id' => $this->employment_type_id,
            'work_mode_id'       => $this->work_mode_id,
            'seniority_level_id' => $this->seniority_level_id,
            'expires_at'         => $this->expires_at?->toIso8601String(),
            'review_status'      => $this->review_status,
            'publication_status' => $this->publication_status,

            // Chỉ hiển thị lý do từ chối / ẩn tin cho Admin hoặc chủ sở hữu doanh nghiệp
            'rejection_reason'   => $this->when($canSeeModerationFields, $this->rejection_reason),
            'hidden_reason'      => $this->when($canSeeModerationFields, $this->hidden_reason),
            'reviewed_by'        => $this->when($canSeeModerationFields, $this->reviewed_by),
            'reviewed_at'        => $this->when($canSeeModerationFields, $this->reviewed_at?->toIso8601String()),

            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),

            'company' => $this->whenLoaded('company', fn () => $this->company ? [
                'id'                  => $this->company->id,
                'company_name'        => $this->company->company_name,
                'description'         => $this->company->description ?? null,
                'website_url'         => $this->company->website_url ?? null,
                'verification_status' => $this->when($canSeeModerationFields, $this->company->verification_status ?? null),
            ] : null),

            'job_group' => $this->whenLoaded('jobGroup', fn () => $this->jobGroup ? [
                'id'   => $this->jobGroup->id,
                'name' => $this->jobGroup->name,
            ] : null),

            'employment_type' => $this->whenLoaded('employmentType', fn () => $this->employmentType ? [
                'id'   => $this->employmentType->id,
                'name' => $this->employmentType->name,
            ] : null),

            'work_mode' => $this->whenLoaded('workMode', fn () => $this->workMode ? [
                'id'   => $this->workMode->id,
                'name' => $this->workMode->name,
            ] : null),

            'seniority_level' => $this->whenLoaded('seniorityLevel', fn () => $this->seniorityLevel ? [
                'id'   => $this->seniorityLevel->id,
                'name' => $this->seniorityLevel->name,
            ] : null),

            'skills' => $this->whenLoaded('skills', fn () => $this->skills->map(fn ($skill) => [
                'id'               => $skill->id,
                'name'             => $skill->name,
                'requirement_type' => $skill->pivot->requirement_type ?? null,
            ])->values()),
        ];
    }
}
