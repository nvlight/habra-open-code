<?php

namespace App\Http\Controllers\Api;

use App\Enums\PublicationStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPublicationRequest;
use App\Http\Requests\StorePublicationRequest;
use App\Http\Requests\UpdatePublicationRequest;
use App\Http\Resources\PublicationListResource;
use App\Http\Resources\PublicationResource;
use App\Models\Publication;
use App\Models\Tag;
use App\Services\PublicationQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicationController extends Controller
{
    public function __construct(
        private readonly PublicationQueryService $queryService,
    ) {}

    public function index(IndexPublicationRequest $request): AnonymousResourceCollection
    {
        $status = $request->string('status', 'published')->toString();

        $query = $this->queryService->baseQuery();

        if ($status === PublicationStatus::Sandbox->value) {
            $query->where('status', PublicationStatus::Sandbox->value);
            $query->orderByDesc('created_at');
        } else {
            $query->published();
        }

        if ($request->filled('hub')) {
            $alias = (string) $request->string('hub');
            $query->whereHas('hubs', fn (Builder $q) => $q->where('alias', $alias));
        }

        if ($request->filled('company')) {
            $slug = (string) $request->string('company');
            $query->whereHas('company', fn (Builder $q) => $q->where('slug', $slug));
        }

        if ($request->filled('author')) {
            $login = (string) $request->string('author');
            $query->whereHas('author', fn (Builder $q) => $q->where('login', $login));
        }

        $this->queryService->applyFilters($query, $request);

        return PublicationListResource::collection($query->paginate($request->integer('per_page') ?: 20));
    }

    public function store(StorePublicationRequest $request): JsonResponse
    {
        $data = $request->validated();
        /** @var PublicationStatus $status */
        $status = isset($data['status']) ? PublicationStatus::from($data['status']) : PublicationStatus::Draft;

        /** @var Publication $publication */
        $publication = $request->user()->publications()->create([
            ...collect($data)->except(['hubs', 'tags', 'status'])->all(),
            'status' => $status,
            'published_at' => now(),
        ]);

        if (isset($data['hubs'])) {
            $publication->hubs()->sync($data['hubs']);
        }

        if (isset($data['tags'])) {
            $tagIds = collect($data['tags'])
                ->map(fn (string $name): Tag => Tag::query()->firstOrCreate(['name' => $name]))
                ->pluck('id');
            $publication->tags()->sync($tagIds);
        }

        return (new PublicationResource(
            $publication->fresh(['author', 'company', 'hubs', 'tags'])
        ))->response()->setStatusCode(201);
    }

    public function show(Request $request, Publication $publication): PublicationResource
    {
        if ($publication->status === PublicationStatus::Draft && $publication->user_id !== $request->user('sanctum')?->id) {
            abort(404);
        }

        if ($publication->status === PublicationStatus::Published) {
            $publication->increment('views_count');
        }

        return new PublicationResource(
            $publication->load(['author', 'company', 'hubs', 'tags'])
                ->loadCount('comments')
        );
    }

    public function update(UpdatePublicationRequest $request, Publication $publication): PublicationResource
    {
        $this->authorize('update', $publication);

        $data = $request->validated();

        $publication->update(collect($data)->except(['hubs', 'tags'])->all());

        if (array_key_exists('hubs', $data)) {
            $publication->hubs()->sync($data['hubs'] ?? []);
        }

        if (array_key_exists('tags', $data)) {
            $tagIds = collect($data['tags'] ?? [])
                ->map(fn (string $name): Tag => Tag::query()->firstOrCreate(['name' => $name]))
                ->pluck('id');
            $publication->tags()->sync($tagIds);
        }

        return new PublicationResource($publication->fresh(['author', 'company', 'hubs', 'tags']));
    }

    public function destroy(Publication $publication): JsonResponse
    {
        $this->authorize('delete', $publication);

        $publication->delete();

        return response()->json(['message' => 'Публикация удалена']);
    }

    public function publish(Request $request, Publication $publication): PublicationResource
    {
        $this->authorize('update', $publication);

        $publication->update([
            'status' => PublicationStatus::Published->value,
            'published_at' => $publication->published_at ?? now(),
        ]);

        return new PublicationResource($publication->fresh(['author', 'company', 'hubs', 'tags']));
    }
}
