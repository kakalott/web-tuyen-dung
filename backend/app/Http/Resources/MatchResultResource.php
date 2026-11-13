<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $matchedSkills = [];
        $missingSkills = [];
        $matchedPrefSkills = [];
        $missingPrefSkills = [];

        if ($this->relationLoaded('matchResultSkills')) {
            foreach ($this->matchResultSkills as $item) {
                $skillData = [
                    'id'   => $item->skill?->id ?? $item->skill_id,
                    'name' => $item->skill?->name ?? '',
                ];

                if ($item->requirement_type === 'required') {
                    if ($item->match_status === 'matched') {
                        $matchedSkills[] = $skillData;
                    } else {
                        $missingSkills[] = $skillData;
                    }
                } else {
                    if ($item->match_status === 'matched') {
                        $matchedPrefSkills[] = $skillData;
                    } else {
                        $missingPrefSkills[] = $skillData;
                    }
                }
            }
        }

        return [
            'id'                     => $this->id,
            'match_score'            => (float) $this->match_score,
            'baseline_score'         => (float) $this->baseline_score,
            'semantic_similarity'    => (float) $this->semantic_similarity,
            'skill_coverage'         => $this->skill_coverage !== null ? (float) $this->skill_coverage : null,
            'required_count'         => (int) $this->required_count,
            'matched_required_count' => (int) $this->matched_required_count,
            'algorithm_version'      => $this->algorithm_version,
            'model_name'             => $this->model_name,
            'matched_skills'         => $matchedSkills,
            'missing_skills'         => $missingSkills,
            'matched_preferred_skills' => $matchedPrefSkills,
            'missing_preferred_skills' => $missingPrefSkills,
            'warnings'               => is_string($this->warnings) ? json_decode($this->warnings, true) : ($this->warnings ?? []),
            'computed_at'            => $this->computed_at?->toIso8601String(),
        ];
    }
}
