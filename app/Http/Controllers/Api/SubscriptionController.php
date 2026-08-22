<?php

namespace App\Http\Controllers\Api;

use App\Enums\SubscribableType;
use App\Http\Controllers\Controller;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\CompanyLiteResource;
use App\Http\Resources\HubResource;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class SubscriptionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Collection<int, Collection<int, Subscription>> $grouped */
        $grouped = $request->user()->subscriptions()->get()->groupBy('subscribable_type');

        return response()->json([
            'users' => AuthorResource::collection(
                User::query()->whereIn('id', $this->idsOf($grouped, User::class))->orderByDesc('rating')->get()
            ),
            'hubs' => HubResource::collection(
                Hub::query()->whereIn('id', $this->idsOf($grouped, Hub::class))->orderByDesc('rating')->get()
            ),
            'companies' => CompanyLiteResource::collection(
                Company::query()->whereIn('id', $this->idsOf($grouped, Company::class))->get()
            ),
        ]);
    }

    public function subscribe(Request $request, string $type, string $key): JsonResponse
    {
        $subscribable = $this->resolve($type, $key);

        if ($subscribable === null) {
            return response()->json(['message' => 'Объект не найден'], 404);
        }

        $subscription = $request->user()->subscriptions()->firstOrCreate([
            'subscribable_type' => $subscribable::class,
            'subscribable_id' => $subscribable->getKey(),
        ]);

        if ($subscription->wasRecentlyCreated) {
            $this->bumpSubscribersCount($subscribable, 1);
        }

        return response()->json(['message' => 'Подписка оформлена', 'subscribed' => true]);
    }

    public function unsubscribe(Request $request, string $type, string $key): JsonResponse
    {
        $subscribable = $this->resolve($type, $key);

        if ($subscribable === null) {
            return response()->json(['message' => 'Объект не найден'], 404);
        }

        $deleted = $request->user()->subscriptions()
            ->where('subscribable_type', $subscribable::class)
            ->where('subscribable_id', $subscribable->getKey())
            ->delete();

        if ($deleted > 0) {
            $this->bumpSubscribersCount($subscribable, -1);
        }

        return response()->json(['message' => 'Подписка отменена', 'subscribed' => false]);
    }

    private function resolve(string $type, string $key): ?Model
    {
        $subscribableType = SubscribableType::tryFrom($type);

        if ($subscribableType === null) {
            return null;
        }

        if (ctype_digit($key)) {
            return match ($subscribableType) {
                SubscribableType::User => User::find((int) $key),
                SubscribableType::Hub => Hub::find((int) $key),
                SubscribableType::Company => Company::find((int) $key),
            };
        }

        return match ($subscribableType) {
            SubscribableType::User => User::query()->where('login', $key)->first(),
            SubscribableType::Hub => Hub::query()->where('alias', $key)->first(),
            SubscribableType::Company => Company::query()->where('slug', $key)->first(),
        };
    }

    /**
     * @param  Collection<int, Collection<int, Subscription>>  $grouped
     * @return list<int>
     */
    private function idsOf(Collection $grouped, string $class): array
    {
        return $grouped
            ->get($class, collect())
            ->pluck('subscribable_id')
            ->all();
    }

    private function bumpSubscribersCount(Model $subscribable, int $delta): void
    {
        if ($subscribable instanceof Hub || $subscribable instanceof Company) {
            $delta >= 0
                ? $subscribable->increment('subscribers_count', $delta)
                : $subscribable->decrement('subscribers_count', abs($delta));
        }
    }
}
