<?php

use App\Models\Comment;
use App\Models\Publication;
use App\Models\User;

it('votes for a publication and recalculates rating', function () {
    $voter = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $this->actingAs($voter, 'sanctum')
        ->postJson("/api/publications/{$publication->id}/vote", ['value' => 1])
        ->assertOk()
        ->assertJsonPath('rating', 1)
        ->assertJsonPath('votes_up', 1);

    expect($publication->refresh())
        ->rating->toBe(1)
        ->votes_up->toBe(1);
});

it('toggles off the same vote', function () {
    $voter = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $endpoint = "/api/publications/{$publication->id}/vote";

    $this->actingAs($voter, 'sanctum')->postJson($endpoint, ['value' => 1])->assertOk();
    $this->actingAs($voter, 'sanctum')->postJson($endpoint, ['value' => 1])->assertOk();

    expect($publication->refresh())
        ->rating->toBe(0)
        ->votes_up->toBe(0)
        ->votes()->count()->toBe(0);
});

it('switches a vote from up to down', function () {
    $voter = User::factory()->create();
    $publication = Publication::factory()->published()->create();
    $endpoint = "/api/publications/{$publication->id}/vote";

    $this->actingAs($voter, 'sanctum')->postJson($endpoint, ['value' => 1]);
    $this->actingAs($voter, 'sanctum')->postJson($endpoint, ['value' => -1]);

    expect($publication->refresh())
        ->rating->toBe(-1)
        ->votes_up->toBe(0)
        ->votes_down->toBe(1);
});

it('aggregates votes from multiple users', function () {
    $users = User::factory()->count(3)->create();
    $publication = Publication::factory()->published()->create();
    $endpoint = "/api/publications/{$publication->id}/vote";

    foreach ($users as $i => $user) {
        $this->actingAs($user, 'sanctum')->postJson($endpoint, ['value' => $i === 2 ? -1 : 1]);
    }

    expect($publication->refresh())
        ->rating->toBe(1)
        ->votes_up->toBe(2)
        ->votes_down->toBe(1);
});

it('requires a value of exactly one or minus one', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/publications/{$publication->id}/vote", ['value' => 5])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('value');
});

it('changes user karma when voting', function () {
    $author = User::factory()->create(['karma' => 10]);
    $voter = User::factory()->create();

    $this->actingAs($voter, 'sanctum')
        ->postJson("/api/users/{$author->id}/karma", ['value' => 1])
        ->assertOk()
        ->assertJsonPath('karma', 11);

    expect($author->refresh()->karma)->toBe(11);
});

it('votes for comments', function () {
    $author = User::factory()->create();
    $voter = User::factory()->create();
    $comment = Comment::factory()->for($author, 'author')->create();

    $this->actingAs($voter, 'sanctum')
        ->postJson("/api/comments/{$comment->id}/vote", ['value' => 1])
        ->assertOk()
        ->assertJsonPath('rating', 1);

    expect($comment->refresh()->rating)->toBe(1);
});
