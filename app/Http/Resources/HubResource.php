<?php

namespace App\Http\Resources;

use App\Models\Hub;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Hub
 */
class HubResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'alias' => $this->alias,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'rating' => $this->rating,
            'subscribers_count' => $this->whenCounted('subscribers') ?? $this->subscribers_count,
        ];
    }
}
