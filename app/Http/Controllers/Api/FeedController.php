<?php

namespace App\Http\Controllers\Api;

use App\Enums\Difficulty;
use App\Enums\PublicationType;
use App\Http\Controllers\Controller;
use App\Http\Resources\PublicationListResource;
use App\Models\Hub;
use App\Models\User;
use App\Services\PublicationQueryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class FeedController extends Controller
{
    public function __construct(
        private readonly PublicationQueryService $queryService,
    ) {}

    public function __invoke(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        $settings = array_merge($user->feed_settings ?? [], $request->only(['min_rating']));
        $types = $this->list($request, 'types', $settings['types'] ?? []);
        $difficulties = $this->list($request, 'difficulties', $settings['difficulties'] ?? []);
        $minRating = (int) ($settings['min_rating'] ?? 0);

        $query = $this->queryService->baseQuery()
            ->published()
            ->where('rating', '>=', $minRating);

        if ($types !== []) {
            $query->whereIn('type', $types);
        }

        if ($difficulties !== []) {
            $query->whereIn('difficulty', $difficulties);
        }

        if (! $request->boolean('global') && $user->subscriptions()->exists()) {
            $hubIds = $user->subscriptions()
                ->where('subscribable_type', Hub::class)
                ->select('subscribable_id');
            $authorIds = $user->subscriptions()
                ->where('subscribable_type', User::class)
                ->select('subscribable_id');

            $query->where(function (Builder $q) use ($hubIds, $authorIds): void {
                $q->whereHas('hubs', fn (Builder $h) => $h->whereIn('hubs.id', $hubIds))
                    ->orWhereIn('user_id', $authorIds);
            });
        }

        $query->orderByDesc((string) $request->string('sort', 'new') === 'best' ? 'rating' : 'published_at');

        return PublicationListResource::collection(
            $query->paginate(min(max($request->integer('per_page') ?: 20, 1), 100))
        );
    }

    /**
     * @return list<string>
     */
    private function list(Request $request, string $key, mixed $fallback): array
    {
        /** @var mixed $raw */
        $raw = $request->input($key, is_array($fallback) ? $fallback : []);

        if (! is_array($raw)) {
            return [];
        }

        $enumClass = $key === 'types' ? PublicationType::class : Difficulty::class;

        return collect($raw)
            ->map(fn ($value): ?string => $enumClass::tryFrom((string) $value)?->value)
            ->filter()
            ->values()
            ->all();
    }
}
