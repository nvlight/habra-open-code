<?php

use App\Models\Company;
use App\Models\Hub;
use App\Models\User;

it('subscribes and unsubscribes to a hub by alias', function () {
    $user = User::factory()->create();
    $hub = Hub::factory()->create(['alias' => 'python', 'subscribers_count' => 0]);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/subscriptions/hub/python')
        ->assertOk()
        ->assertJsonPath('subscribed', true);

    expect($hub->refresh()->subscribers_count)->toBe(1);

    $this->actingAs($user, 'sanctum')
        ->deleteJson('/api/subscriptions/hub/python')
        ->assertOk();

    expect($hub->refresh()->subscribers_count)->toBe(0)
        ->and($user->subscriptions()->count())->toBe(0);
});

it('subscribes to a user by login', function () {
    $follower = User::factory()->create();
    $author = User::factory()->create(['login' => 'popular_author']);

    $this->actingAs($follower, 'sanctum')
        ->postJson('/api/subscriptions/user/popular_author')
        ->assertOk();

    $followers = $this->getJson('/api/users/popular_author/followers')->json('data');

    expect(collect($followers)->pluck('login')->all())->toContain($follower->login);
});

it('subscribes to a company by slug', function () {
    $user = User::factory()->create();
    Company::factory()->create(['slug' => 'acme']);

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/subscriptions/company/acme')
        ->assertOk();

    expect($this->getJson('/api/companies/acme')->json('data.subscribers_count'))->toBe(1);
});

it('returns unknown object on bad subscription key', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->postJson('/api/subscriptions/hub/no_such_hub')
        ->assertNotFound();
});

it('groups subscriptions by type in index endpoint', function () {
    $user = User::factory()->create();
    $hub = Hub::factory()->create();
    $company = Company::factory()->create();
    $friend = User::factory()->create();

    $user->subscriptions()->createMany([
        ['subscribable_type' => Hub::class, 'subscribable_id' => $hub->id],
        ['subscribable_type' => Company::class, 'subscribable_id' => $company->id],
        ['subscribable_type' => User::class, 'subscribable_id' => $friend->id],
    ]);

    $response = $this->actingAs($user, 'sanctum')->getJson('/api/subscriptions');

    $response->assertOk();
    expect($response->json('hubs.0.id'))->toBe($hub->id)
        ->and($response->json('companies.0.slug'))->toBe($company->slug)
        ->and($response->json('users.0.login'))->toBe($friend->login);
});
