<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class NotificationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'event_type'   => $this->event_type,
            'content'      => $this->content,
            'related_type' => $this->related_type,
            'related_id'   => $this->related_id,
            'is_read'      => $this->read_at !== null,
            'read_at'      => $this->read_at?->toIso8601String(),
            'created_at'   => $this->created_at?->toIso8601String(),
        ];
    }
}
