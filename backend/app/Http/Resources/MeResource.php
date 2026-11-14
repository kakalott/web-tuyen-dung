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

        $companyData = null;
        if ($this->role === 'company' && $this->company) {
            $companyData = [
                'id'                  => $this->company->id,
                'company_name'        => $this->company->company_name,
                'verification_status' => $this->company->verification_status,
                'verified_at'         => $this->company->verified_at?->toIso8601String(),
            ];
        }

        return [
            'id'         => $this->id,
            'name'       => $this->name,
            'role'       => $this->role,
            'profile_id' => $profileId,
            'company'    => $companyData,
        ];
    }
}
