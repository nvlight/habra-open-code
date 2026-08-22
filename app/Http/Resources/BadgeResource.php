<?php

namespace App\Http\Resources;

use App\Models\Badge;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Badge
 *
 * @property Model|null $pivot
 */
class BadgeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'awarded_at' => $this->whenPivotLoaded('badge_user', fn (): mixed => data_get($this->pivot, 'awarded_at')),
        ];
    }
}
