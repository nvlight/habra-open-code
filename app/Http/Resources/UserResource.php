<?php

namespace App\Http\Resources;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'login' => $this->login,
            'name' => $this->name,
            'about' => $this->about,
            'avatar' => $this->avatar,
            'karma' => $this->karma,
            'rating' => $this->rating,
            'location' => $this->location,
            'company' => new CompanyLiteResource($this->whenLoaded('company')),
            'badges' => BadgeResource::collection($this->whenLoaded('badges')),
            'publications_count' => $this->whenCounted('publications'),
            'comments_count' => $this->whenCounted('comments'),
            'followers_count' => $this->whenCounted('subscribers'),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
