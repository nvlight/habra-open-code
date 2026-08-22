<?php

namespace App\Http\Controllers\Api;

use App\Enums\VoteSubject;
use App\Http\Controllers\Controller;
use App\Http\Requests\VoteRequest;
use App\Models\Comment;
use App\Models\Publication;
use App\Models\User;
use App\Services\VoteService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;

class VoteController extends Controller
{
    public function __construct(
        private readonly VoteService $votes,
    ) {}

    public function votePublication(VoteRequest $request, Publication $publication): JsonResponse
    {
        return $this->castAndRespond($request, VoteSubject::Publication, $publication->getKey());
    }

    public function voteComment(VoteRequest $request, Comment $comment): JsonResponse
    {
        return $this->castAndRespond($request, VoteSubject::Comment, $comment->getKey());
    }

    public function voteUser(VoteRequest $request, User $user): JsonResponse
    {
        return $this->castAndRespond($request, VoteSubject::User, $user->getKey());
    }

    private function castAndRespond(VoteRequest $request, VoteSubject $subject, int $targetId): JsonResponse
    {
        $target = $this->votes->vote(
            $request->user(),
            $subject,
            $targetId,
            (int) $request->integer('value'),
        );

        /** @var Model|User|null $target */
        if ($target === null) {
            return response()->json(['message' => 'Объект не найден'], 404);
        }

        $payload = match ($subject) {
            VoteSubject::Publication => [
                'rating' => $target->getAttribute('rating'),
                'votes_up' => $target->getAttribute('votes_up'),
                'votes_down' => $target->getAttribute('votes_down'),
            ],
            VoteSubject::Comment => ['rating' => $target->getAttribute('rating')],
            VoteSubject::User => ['karma' => $target->getAttribute('karma')],
        };

        return response()->json($payload);
    }
}
