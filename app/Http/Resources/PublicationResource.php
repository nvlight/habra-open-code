<?php

namespace App\Http\Resources;

use App\Models\Publication;
use Illuminate\Http\Request;

/**
 * @mixin Publication
 */
class PublicationResource extends PublicationListResource
{
    public function toArray(Request $request): array
    {
        return array_merge(parent::toArray($request), [
            'body' => $this->body,
            'source_url' => $this->source_url,
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ]);
    }
}
