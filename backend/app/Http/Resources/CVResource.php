<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CVResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $extraction = $this->relationLoaded('latestExtraction') ? $this->latestExtraction : null;

        $skills = [];
        if ($extraction && $extraction->relationLoaded('skills')) {
            $skills = $extraction->skills
                ->filter(fn ($skill) => ($skill->pivot->review_status ?? '') !== 'rejected')
                ->map(fn ($skill) => [
                    'id'            => $skill->id,
                    'skill_id'      => $skill->id,
                    'name'          => $skill->name,
                    'review_status' => $skill->pivot->review_status ?? 'suggested',
                ])->values()->all();
        }

        // Ẩn hoàn toàn trường storage_key và deleted_at
        return [
            'id'                => $this->id,
            'original_filename' => $this->original_filename,
            'file_size'         => $this->file_size,
            'mime_type'         => $this->mime_type,
            'parse_status'      => $this->parse_status,
            'uploaded_at'       => $this->uploaded_at,
            'review_status'     => $extraction?->review_status,
            'skills'            => $skills,

            // Load Extraction nếu có join (phục vụ API-CV-04 hoặc chi tiết)
            'extraction'        => $this->whenLoaded('latestExtraction', function () use ($extraction, $skills) {
                if (!$extraction) {
                    return null;
                }
                return [
                    'id'             => $extraction->id,
                    'review_status'  => $extraction->review_status,
                    'extracted_text' => $extraction->extracted_text,
                    'confirmed_text' => $extraction->confirmed_text,
                    'warnings'       => $extraction->warnings,
                    'skills'         => $skills,
                ];
            }),
        ];
    }
}
