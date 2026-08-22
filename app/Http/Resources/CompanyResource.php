<?php

namespace App\Http\Resources;

use App\Models\Company;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Company
 */
class CompanyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'description' => $this->description,
            'avatar' => $this->avatar,
            'website' => $this->website,
            'rating' => $this->rating,
            'location' => $this->location,
            'size' => $this->size,
            'founded_at' => $this->founded_at?->toDateString(),
            'representative' => new AuthorResource($this->whenLoaded('representative')),
            'industries' => IndustryResource::collection($this->whenLoaded('industries')),
            'employees_count' => $this->whenCounted('employees'),
            'publications_count' => $this->whenCounted('publications'),
            'subscribers_count' => $this->subscribers_count,
        ];
    }
}
