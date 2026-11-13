<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class InterviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                 => $this->id,
            'application_id'     => $this->application_id,
            'scheduled_at'       => $this->scheduled_at?->toIso8601String(),
            'location_or_mode'   => $this->location_or_mode,
            'notes'              => $this->notes,
            'cancel_reason'      => $this->cancel_reason,
            'status'             => $this->status,
            'created_by_user_id' => $this->created_by_user_id,
            'created_at'         => $this->created_at?->toIso8601String(),
            'updated_at'         => $this->updated_at?->toIso8601String(),
        ];
    }
}
