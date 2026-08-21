<?php

namespace App\Http\Controllers\Api;

use App\Enums\PublicationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPublicationRequest;
use App\Http\Resources\HubResource;
use App\Http\Resources\PublicationListResource;
use App\Models\Hub;
use App\Models\Publication;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class HubController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return HubResource::collection(
            Hub::query()->orderByDesc('rating')->paginate(30)
        );
    }

    public function show(string $alias): HubResource
    {
        /** @var Hub $hub */
        $hub = Hub::query()->where('alias', $alias)->firstOrFail();

        return new HubResource($hub);
    }

    public function publications(string $alias, IndexPublicationRequest $request): AnonymousResourceCollection
    {
        $query = Publication::query()
            ->with(['author', 'company', 'hubs', 'tags'])
            ->whereHas('hubs', fn (Builder $q) => $q->where('alias', $alias))
            ->published();

        if ($request->filled('type')) {
            $query->type(PublicationType::from((string) $request->string('type')));
        }

        if ($request->filled('difficulty')) {
            $query->where('difficulty', $request->string('difficulty'));
        }

        if ($request->filled('min_rating')) {
            $query->where('rating', '>=', $request->integer('min_rating'));
        }

        if ($request->filled('label')) {
            $query->where('label', $request->string('label'));
        }

        $query->reorder()->orderByDesc(
            (string) $request->string('sort', 'new') === 'best' ? 'rating' : 'published_at'
        );

        return PublicationListResource::collection($query->paginate($request->integer('per_page') ?: 20));
    }
}
