<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PublicationListResource;
use App\Models\Publication;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class BookmarkController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        /** @var User $user */
        $user = $request->user();

        return PublicationListResource::collection(
            $user->bookmarkedPublications()
                ->with(['author', 'company', 'hubs', 'tags'])
                ->latest('bookmarks.created_at')
                ->paginate(20)
        );
    }

    public function store(Request $request, Publication $publication): PublicationListResource
    {
        $created = $publication->bookmarkedBy()->syncWithoutDetaching([$request->user()->id]);

        if ($created['attached'] !== []) {
            $publication->increment('bookmarks_count');
        }

        return new PublicationListResource($publication);
    }

    public function destroy(Request $request, Publication $publication): Publication
    {
        $detached = (bool) $publication->bookmarkedBy()->detach([$request->user()->id]);

        if ($detached) {
            $publication->decrement('bookmarks_count');
        }

        return $publication;
    }
}
