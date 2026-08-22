<?php

namespace App\Services;

use App\Enums\PublicationType;
use App\Http\Requests\IndexPublicationRequest;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;

class PublicationQueryService
{
    /**
     * @return Builder<Publication>
     */
    public function baseQuery(): Builder
    {
        return Publication::query()->with(['author', 'company', 'hubs', 'tags']);
    }

    /**
     * Applies shared list filters (type, difficulty, label, min_rating) and sorting.
     *
     * @param  Builder<Publication>  $query
     * @return Builder<Publication>
     */
    public function applyFilters(Builder $query, IndexPublicationRequest $request): Builder
    {
        if ($request->filled('type')) {
            $query->type(PublicationType::from((string) $request->string('type')));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->string('difficulty'));
        }

        if ($request->filled('label')) {
            $query->where('label', $request->string('label'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->integer('min_rating'));
        }

        $query->reorder()->orderByDesc(
            (string) $request->string('sort', 'new') === 'best' ? 'rating' : 'published_at'
        );

        return $query;
    }
}
