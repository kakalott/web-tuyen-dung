<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class MeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $profileId = match ($this->role) {
            'candidate' => $this->candidateProfile?->id,
            'company'   => $this->company?->id,
            default     => null,
        };

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'role'       => $this->role,
            'profile_id' => $profileId,
        ];
    }
}
