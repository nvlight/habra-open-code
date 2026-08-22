<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Publication
 */
class PublicationListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'type_label' => $this->type->label(),
            'status' => $this->status,
            'title' => $this->title,
            'lead' => $this->lead,
            'cover' => $this->cover,
            'difficulty' => $this->difficulty,
            'difficulty_label' => $this->difficulty?->label(),
            'label' => $this->label,
            'label_label' => $this->label?->label(),
            'is_translation' => $this->is_translation,
            'original_author' => $this->original_author,
            'is_recovery_mode' => $this->is_recovery_mode,
            'reading_time' => $this->reading_time,
            'views_count' => $this->views_count,
            'reach' => $this->reach,
            'rating' => $this->rating,
            'votes_up' => $this->votes_up,
            'votes_down' => $this->votes_down,
            'comments_count' => $this->comments_count,
            'bookmarks_count' => $this->bookmarks_count,
            'published_at' => $this->published_at?->toIso8601String(),
            'author' => new AuthorResource($this->whenLoaded('author')),
            'company' => new CompanyLiteResource($this->whenLoaded('company')),
            'hubs' => HubResource::collection($this->whenLoaded('hubs')),
            'tags' => TagResource::collection($this->whenLoaded('tags')),
        ];
    }
}
