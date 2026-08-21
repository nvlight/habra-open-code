<?php

use App\Models\Publication;
use App\Models\User;

it('adds and removes a bookmark', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/publications/{$publication->id}/bookmark")
        ->assertOk();

    expect($publication->refresh()->bookmarks_count)->toBe(1)
        ->and($user->bookmarkedPublications)->toHaveCount(1);

    $this->actingAs($user, 'sanctum')
        ->deleteJson("/api/publications/{$publication->id}/bookmark")
        ->assertOk();

    expect($publication->refresh()->bookmarks_count)->toBe(0)
        ->and($user->bookmarkedPublications()->count())->toBe(0);
});

it('does not duplicate bookmarks', function () {
    $user = User::factory()->create();
    $publication = Publication::factory()->published()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/publications/{$publication->id}/bookmark");
    $this->actingAs($user, 'sanctum')->postJson("/api/publications/{$publication->id}/bookmark");

    expect($publication->refresh()->bookmarks_count)->toBe(1);
});

it('lists bookmarked publications', function () {
    $user = User::factory()->create();
    $saved = Publication::factory()->published()->create();
    $other = Publication::factory()->published()->create();

    $user->bookmarkedPublications()->sync([$saved->id]);
    $saved->update(['bookmarks_count' => 1]);

    $data = $this->actingAs($user, 'sanctum')
        ->getJson('/api/bookmarks')
        ->assertOk()
        ->json('data');

    expect(collect($data)->pluck('id')->all())->toEqual([$saved->id])
        ->and($data)->not->toContain($other->id);
});

it('requires authentication for bookmarks', function () {
    $publication = Publication::factory()->published()->create();

    $this->getJson('/api/bookmarks')->assertUnauthorized();
    $this->postJson("/api/publications/{$publication->id}/bookmark")->assertUnauthorized();
});
