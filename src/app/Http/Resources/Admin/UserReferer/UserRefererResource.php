<?php

namespace App\Http\Resources\Admin\UserReferer;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class UserRefererResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'user' => $this->user ? $this->user->only(['id', 'name']) : null,
            'ip_address' => $this->ip_address,
            'referer_url' => $this->referer_url,
            'landing_page' => $this->landing_page,
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_term' => $this->utm_term,
            'utm_content' => $this->utm_content,
            'user_agent' => $this->user_agent,
            'created_at' => $this->created_at->format('d.m.Y H:i'),
            'created' => $this->created_at->diffForHumans(),
        ];
    }
}
