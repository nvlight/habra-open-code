<?php

use App\Models\Comment;
use App\Models\Publication;
use App\Models\User;

it('creates a comment on a publication', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/publications/{$publication->id}/comments", [
        'body' => 'Отличная статья, спасибо!',
    ]);

    $response->assertCreated()->assertJsonPath('data.body', 'Отличная статья, спасибо!');
    expect($publication->refresh()->comments_count)->toBe(1);
});

it('returns hydrated db defaults for a freshly created comment', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson("/api/publications/{$publication->id}/comments", [
        'body' => 'Новый комментарий',
    ]);

    $response->assertCreated()
        ->assertJsonPath('data.rating', 0)
        ->assertJsonPath('data.author.login', $user->login)
        ->assertJsonPath('data.parent_id', null);
});

it('requires authentication to comment', function () {
    $publication = Publication::factory()->published()->create();

    $this->postJson("/api/publications/{$publication->id}/comments", [
        'body' => 'Спам',
    ])->assertUnauthorized();
});

it('rejects a reply pointing to another publication', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();
    $other = Publication::factory()->published()->create();

    $foreignComment = $other->comments()->create([
        'user_id' => $user->id,
        'body' => 'Чужой комментарий',
    ]);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/publications/{$publication->id}/comments", [
            'body' => 'Ответ не туда',
            'parent_id' => $foreignComment->id,
        ])->assertNotFound();
});

it('returns a nested comment tree', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $root = $publication->comments()->create(['user_id' => $user->id, 'body' => 'Корень']);
    $reply = $publication->comments()->create(['user_id' => $user->id, 'body' => 'Ответ', 'parent_id' => $root->id]);
    $deepReply = $publication->comments()->create(['user_id' => $user->id, 'body' => 'Глубокий ответ', 'parent_id' => $reply->id]);

    $data = $this->getJson("/api/publications/{$publication->id}/comments")->assertOk()->json('data');

    expect($data)->toHaveCount(1)
        ->and($data[0]['body'])->toBe('Корень')
        ->and($data[0]['replies'][0]['body'])->toBe('Ответ')
        ->and($data[0]['replies'][0]['replies'][0]['body'])->toBe('Глубокий ответ')
        ->and(Comment::query()->count())->toBe(3);
});

it('allows only the author to delete a comment', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $comment = $publication->comments()->create([
        'user_id' => $owner->id,
        'body' => 'Комментарий автора',
    ]);
    $publication->forceFill(['comments_count' => 1])->save();

    $this->actingAs($intruder, 'sanctum')
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/comments/{$comment->id}")
        ->assertOk();

    expect($publication->refresh()->comments_count)->toBe(0);
});
