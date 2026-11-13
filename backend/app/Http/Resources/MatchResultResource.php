<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MatchResultResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $skillsCollection = $this->whenLoaded('matchResultSkills', fn () => $this->matchResultSkills, collect());

        $matchedRequired = $skillsCollection
            ->filter(fn ($item) => $item->requirement_type === 'required' && $item->match_status === 'matched')
            ->map(fn ($item) => [
                'id'   => $item->skill_id,
                'name' => $item->relationLoaded('skill') ? $item->skill?->name : null,
            ])
            ->values();

        $missingRequired = $skillsCollection
            ->filter(fn ($item) => $item->requirement_type === 'required' && $item->match_status === 'missing')
            ->map(fn ($item) => [
                'id'   => $item->skill_id,
                'name' => $item->relationLoaded('skill') ? $item->skill?->name : null,
            ])
            ->values();

        $matchedPreferred = $skillsCollection
            ->filter(fn ($item) => $item->requirement_type === 'preferred' && $item->match_status === 'matched')
            ->map(fn ($item) => [
                'id'   => $item->skill_id,
                'name' => $item->relationLoaded('skill') ? $item->skill?->name : null,
            ])
            ->values();

        return [
            'id'                     => $this->id,
            'baseline_score'         => (float) $this->baseline_score,
            'semantic_similarity'    => (float) $this->semantic_similarity,
            'skill_coverage'         => $this->skill_coverage !== null ? (float) $this->skill_coverage : null,
            'match_score'            => (float) $this->match_score,
            'required_count'         => (int) $this->required_count,
            'matched_required_count' => (int) $this->matched_required_count,
            'matched_skills'         => $matchedRequired,
            'missing_skills'         => $missingRequired,
            'matched_preferred_skills' => $matchedPreferred,
            'model_name'             => $this->model_name,
            'algorithm_version'      => $this->algorithm_version,
            'warnings'               => is_string($this->warnings) ? json_decode($this->warnings, true) : ($this->warnings ?? []),
            'score_stale'            => (bool) ($this->score_stale ?? $this->is_stale ?? false),
            'computed_at'            => $this->computed_at?->toIso8601String(),
        ];
    }
}
