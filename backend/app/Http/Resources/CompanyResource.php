<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'user_id'             => $this->user_id,
            'company_name'        => $this->company_name,
            'description'         => $this->description,
            'website_url'         => $this->website_url,
            'verification_status' => $this->verification_status,
            'verification_note'   => $this->verification_note,
            'verified_by'         => $this->verified_by,
            'verified_at'         => $this->verified_at?->toIso8601String(),
            'created_at'          => $this->created_at?->toIso8601String(),
            'updated_at'          => $this->updated_at?->toIso8601String(),

            'user' => $this->whenLoaded('user', fn () => [
                'id'             => $this->user->id,
                'name'           => $this->user->name,
                'email'          => $this->user->email,
                'account_status' => $this->user->account_status ?? null,
            ]),

            'verifier' => $this->whenLoaded('verifier', fn () => $this->verifier ? [
                'id'    => $this->verifier->id,
                'name'  => $this->verifier->name,
                'email' => $this->verifier->email,
            ] : null),
        ];
    }
}
