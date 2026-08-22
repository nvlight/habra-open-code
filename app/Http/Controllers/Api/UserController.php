<?php

namespace App\Http\Controllers\Api;

use App\Enums\PublicationType;
use App\Http\Controllers\Controller;
use App\Http\Requests\IndexPublicationRequest;
use App\Http\Requests\UpdateProfileRequest;
use App\Http\Resources\AuthorResource;
use App\Http\Resources\CommentResource;
use App\Http\Resources\PublicationListResource;
use App\Http\Resources\UserResource;
use App\Models\Comment;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class UserController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        $users = User::query()
            ->withCount(['publications', 'subscribers'])
            ->orderByDesc('rating')
            ->paginate(20);

        return UserResource::collection($users);
    }

    public function show(string $login): UserResource
    {
        /** @var User $user */
        $user = User::query()
            ->where('login', $login)
            ->with(['company', 'badges'])
            ->withCount(['publications', 'comments', 'subscribers'])
            ->firstOrFail();

        return new UserResource($user);
    }

    public function publications(string $login, IndexPublicationRequest $request): AnonymousResourceCollection
    {
        $query = Publication::query()
            ->with(['author', 'company', 'hubs', 'tags'])
            ->whereHas('author', fn (Builder $q) => $q->where('login', $login))
            ->published();

        if ($request->filled('type')) {
            $query->type(PublicationType::from((string) $request->string('type')));
        }

        $query->reorder()->orderByDesc(
            (string) $request->string('sort', 'new') === 'best' ? 'rating' : 'published_at'
        );

        return PublicationListResource::collection($query->paginate($request->integer('per_page') ?: 20));
    }

    public function updateProfile(UpdateProfileRequest $request): UserResource
    {
        /** @var User $user */
        $user = $request->user();

        $user->update($request->validated());

        return new UserResource($user->fresh());
    }

    public function comments(string $login): AnonymousResourceCollection
    {
        $comments = Comment::query()
            ->with('author')
            ->whereHas('author', fn (Builder $q) => $q->where('login', $login))
            ->latest()
            ->paginate(20);

        return CommentResource::collection($comments);
    }

    public function followers(string $login): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = User::query()->where('login', $login)->firstOrFail();

        $followers = User::query()
            ->whereHas('subscriptions', fn (Builder $q) => $q
                ->where('subscribable_type', User::class)
                ->where('subscribable_id', $user->id))
            ->orderByDesc('rating')
            ->paginate(20);

        return AuthorResource::collection($followers);
    }

    public function following(string $login): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = User::query()->where('login', $login)->firstOrFail();

        $following = User::query()
            ->whereIn('id', $user->subscriptions()
                ->where('subscribable_type', User::class)
                ->select('subscribable_id'))
            ->orderByDesc('rating')
            ->paginate(20);

        return AuthorResource::collection($following);
    }
}
