<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CVResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Ẩn hoàn toàn trường storage_key và deleted_at
        return [
            'id'                => $this->id,
            'original_filename' => $this->original_filename,
            'file_size'         => $this->file_size,
            'mime_type'         => $this->mime_type,
            'parse_status'      => $this->parse_status,
            'uploaded_at'       => $this->uploaded_at,
            
            // Load Extraction nếu có join (phục vụ API-CV-04)
            'extraction'        => $this->whenLoaded('latestExtraction', function () {
                return [
                    'id'             => $this->latestExtraction->id,
                    'review_status'  => $this->latestExtraction->review_status,
                    'extracted_text' => $this->latestExtraction->extracted_text,
                    'confirmed_text' => $this->latestExtraction->confirmed_text,
                    'warnings'       => $this->latestExtraction->warnings,
                    'skills'         => $this->whenLoaded('skills', function () {
                         return $this->latestExtraction->skills->map(function ($skill) {
                             return [
                                 'skill_id'      => $skill->id,
                                 'review_status' => $skill->pivot->review_status,
                                 'source'        => $skill->pivot->source,
                             ];
                         });
                    }),
                ];
            }),
        ];
    }
}
