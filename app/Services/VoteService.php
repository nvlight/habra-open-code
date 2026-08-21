<?php

namespace App\Services;

use App\Enums\VoteSubject;
use App\Models\Comment;
use App\Models\Publication;
use App\Models\User;
use App\Models\Vote;
use Illuminate\Database\Eloquent\Model;

class VoteService
{
    /**
     * Cast, switch or toggle a vote and refresh denormalized counters.
     */
    public function vote(User $user, VoteSubject $subject, int $id, int $value): ?Model
    {
        $target = $this->resolveTarget($subject, $id);

        if ($target === null) {
            return null;
        }

        /** @var Vote|null $existing */
        $existing = $user->votes()
            ->where('voteable_type', $target->getMorphClass())
            ->where('voteable_id', $target->getKey())
            ->first();

        if ($existing !== null && $existing->value === $value) {
            $existing->delete();
            $delta = -$value;
        } elseif ($existing !== null) {
            $existing->update(['value' => $value]);
            $delta = 2 * $value;
        } else {
            $user->votes()->create([
                'voteable_type' => $target->getMorphClass(),
                'voteable_id' => $target->getKey(),
                'value' => $value,
            ]);
            $delta = $value;
        }

        if ($subject === VoteSubject::User && $target instanceof User) {
            $target->increment('karma', $delta);
        } elseif ($subject === VoteSubject::Publication && $target instanceof Publication) {
            $this->recalculatePublication($target);
        } elseif ($subject === VoteSubject::Comment && $target instanceof Comment) {
            $this->recalculateComment($target);
        }

        return $target->refresh();
    }

    public function resolveTarget(VoteSubject $subject, int $id): ?Model
    {
        return match ($subject) {
            VoteSubject::Publication => Publication::find($id),
            VoteSubject::Comment => Comment::find($id),
            VoteSubject::User => User::find($id),
        };
    }

    private function recalculatePublication(Publication $publication): void
    {
        $up = (int) $publication->votes()->where('value', 1)->count();
        $down = (int) $publication->votes()->where('value', -1)->count();

        $publication->forceFill([
            'votes_up' => $up,
            'votes_down' => $down,
            'rating' => $up - $down,
        ])->save();
    }

    private function recalculateComment(Comment $comment): void
    {
        $comment->forceFill([
            'rating' => (int) $comment->votes()->sum('value'),
        ])->save();
    }
}
