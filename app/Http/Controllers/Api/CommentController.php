<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Comment;
use App\Models\Publication;
use Illuminate\Http\JsonResponse;

class CommentController extends Controller
{
    public function destroy(Comment $comment): JsonResponse
    {
        $this->authorize('delete', $comment);

        $publication = $comment->publication;

        $comment->delete();

        if ($publication instanceof Publication) {
            $publication->forceFill([
                'comments_count' => $publication->comments()->count(),
            ])->save();
        }

        return response()->json(['message' => 'Комментарий удалён']);
    }
}
