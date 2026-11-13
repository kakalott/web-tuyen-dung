<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class AdminDashboardResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'users_total'          => (int) ($this->resource['users_total'] ?? 0),
            'companies_unverified' => (int) ($this->resource['companies_unverified'] ?? 0),
            'jobs_pending'         => (int) ($this->resource['jobs_pending'] ?? 0),
            'applications_total'   => (int) ($this->resource['applications_total'] ?? 0),
        ];
    }
}
