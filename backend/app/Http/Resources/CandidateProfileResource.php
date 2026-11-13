<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CandidateProfileResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Hỗ trợ cả đầu vào là mảng ['profile' => ..., 'educations' => ..., 'experiences' => ...]
        // từ CandidateProfileService::show() hoặc trực tiếp là Model CandidateProfile từ update()
        $isArray = is_array($this->resource);
        $profile = $isArray ? $this->resource['profile'] : $this->resource;

        $data = [
            'id'         => $profile->id,
            'user_id'    => $profile->user_id,
            'summary'    => $profile->summary,
            'phone'      => $profile->phone,
            'location'   => $profile->location,
            'updated_at' => $profile->updated_at?->toIso8601String(),
        ];

        if ($isArray) {
            $data['educations'] = collect($this->resource['educations'])->map(fn ($edu) => [
                'id'             => $edu->id,
                'institution'    => $edu->institution,
                'degree'         => $edu->degree,
                'field_of_study' => $edu->field_of_study,
                'start_date'     => $edu->start_date,
                'end_date'       => $edu->end_date,
                'description'    => $edu->description,
            ])->values();

            $data['experiences'] = collect($this->resource['experiences'])->map(fn ($exp) => [
                'id'            => $exp->id,
                'employer_name' => $exp->employer_name,
                'job_title'     => $exp->job_title,
                'start_date'    => $exp->start_date,
                'end_date'      => $exp->end_date,
                'description'   => $exp->description,
            ])->values();
        }

        return $data;
    }
}
