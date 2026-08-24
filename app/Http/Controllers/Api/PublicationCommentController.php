<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCommentRequest;
use App\Http\Resources\CommentResource;
use App\Models\Comment;
use App\Models\Publication;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class PublicationCommentController extends Controller
{
    public function index(Publication $publication): AnonymousResourceCollection
    {
        $comments = $publication->comments()
            ->with([
                'author',
                'replies.author',
                'replies.replies.author',
                'replies.replies.replies.author',
            ])
            ->whereNull('parent_id')
            ->latest()
            ->get();

        return CommentResource::collection($comments);
    }

    public function store(StoreCommentRequest $request, Publication $publication): JsonResponse
    {
        $data = $request->validated();

        if (! empty($data['parent_id'])) {
            Comment::query()
                ->where('id', $data['parent_id'])
                ->where('publication_id', $publication->id)
                ->firstOrFail();
        }

        /** @var Comment $comment */
        $comment = $publication->comments()->create([
            'user_id' => $request->user()->id,
            'parent_id' => $data['parent_id'] ?? null,
            'body' => $data['body'],
        ]);

        $publication->increment('comments_count');

        return (new CommentResource($comment->fresh('author')))
            ->response()
            ->setStatusCode(201);
    }
}
