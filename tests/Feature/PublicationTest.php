<?php

use App\Enums\PublicationStatus;
use App\Models\Company;
use App\Models\Hub;
use App\Models\Publication;
use App\Models\User;

it('lists published publications for guests', function () {
    Publication::factory()->count(3)->create();
    Publication::factory()->draft()->create();

    $response = $this->getJson('/api/publications');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(3);
});

it('filters publications by type', function () {
    Publication::factory()->count(2)->create();
    Publication::factory()->news()->create();

    $response = $this->getJson('/api/publications?type=news');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.type'))->toBe('news');
});

it('filters publications by hub alias', function () {
    $hub = Hub::factory()->create(['alias' => 'python']);
    Publication::factory()->published()->create()->hubs()->sync([$hub->id]);
    Publication::factory()->published()->create();

    $response = $this->getJson('/api/publications?hub=python');

    expect($response->json('data'))->toHaveCount(1);
});

it('filters publications by minimum rating', function () {
    $high = Publication::factory()->published()->create();
    $high->forceFill(['rating' => 42])->save();
    Publication::factory()->published()->create();

    $response = $this->getJson('/api/publications?min_rating=10');

    expect($response->json('data'))->toHaveCount(1)
        ->and($response->json('data.0.rating'))->toBeGreaterThanOrEqual(10);
});

it('sorts best publications by rating', function () {
    $low = Publication::factory()->published()->create();
    $low->forceFill(['rating' => 1])->save();
    $top = Publication::factory()->published()->create();
    $top->forceFill(['rating' => 99])->save();

    $data = $this->getJson('/api/publications?sort=best')->json('data');

    expect((int) $data[0]['rating'])->toBeGreaterThanOrEqual((int) $data[1]['rating']);
});

it('shows a single publication and counts a view', function () {
    $publication = Publication::factory()->published()->create();
    $initial = $publication->views_count;

    $this->getJson("/api/publications/{$publication->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $publication->id)
        ->assertJsonPath('data.body', $publication->body);

    expect($publication->refresh()->views_count)->toBe($initial + 1);
});

it('hides drafts from guests and shows them to the author', function () {
    $author = User::factory()->create();
    $publication = Publication::factory()->for($author, 'author')->draft()->create();

    $this->getJson("/api/publications/{$publication->id}")->assertNotFound();
    $this->actingAs($author, 'sanctum')
        ->getJson("/api/publications/{$publication->id}")
        ->assertOk();
});

it('creates a publication with hubs and tags when authenticated', function () {
    $user = User::factory()->create();
    $hub = Hub::factory()->create();

    $response = $this->actingAs($user, 'sanctum')->postJson('/api/publications', [
        'title' => 'Тестовая статья про Laravel',
        'lead' => 'Краткое описание',
        'body' => '# Заголовок'.PHP_EOL.'Текст статьи',
        'type' => 'article',
        'status' => 'sandbox',
        'difficulty' => 'medium',
        'label' => 'tutorial',
        'hubs' => [$hub->id],
        'tags' => ['laravel', 'php'],
    ]);

    $response->assertCreated()->assertJsonPath('data.title', 'Тестовая статья про Laravel');

    $publication = Publication::query()->findOrFail($response->json('data.id'));

    expect($publication->status)->toBe(PublicationStatus::Sandbox)
        ->and($publication->hubs)->toHaveCount(1)
        ->and($publication->tags->pluck('name')->all())->toEqualCanonicalizing(['laravel', 'php']);
});

it('requires authentication to create a publication', function () {
    $this->postJson('/api/publications', [
        'title' => 'Анонимная статья',
        'body' => 'Текст',
        'type' => 'article',
    ])->assertUnauthorized();
});

it('allows only the author to update a publication', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $publication = Publication::factory()->for($owner, 'author')->create();

    $this->actingAs($intruder, 'sanctum')
        ->putJson("/api/publications/{$publication->id}", ['title' => 'Взломано'])
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->putJson("/api/publications/{$publication->id}", ['title' => 'Обновлённый заголовок'])
        ->assertOk()
        ->assertJsonPath('data.title', 'Обновлённый заголовок');
});

it('allows only the author to delete a publication', function () {
    $owner = User::factory()->create();
    $intruder = User::factory()->create();
    $publication = Publication::factory()->for($owner, 'author')->create();

    $this->actingAs($intruder, 'sanctum')
        ->deleteJson("/api/publications/{$publication->id}")
        ->assertForbidden();

    $this->actingAs($owner, 'sanctum')
        ->deleteJson("/api/publications/{$publication->id}")
        ->assertOk();

    expect(Publication::query()->find($publication->id))->toBeNull();
});

it('publishes a sandbox publication by its author', function () {
    $author = User::factory()->create();
    $publication = Publication::factory()->for($author, 'author')->sandbox()->create();

    $this->actingAs($author, 'sanctum')
        ->postJson("/api/publications/{$publication->id}/publish")
        ->assertOk()
        ->assertJsonPath('data.status', 'published');

    expect($publication->refresh()->published_at)->not->toBeNull();
});

it('lists corporate publications of a company', function () {
    $company = Company::factory()->create();
    Publication::factory()->published()->count(2)->create(['company_id' => $company->id]);
    Publication::factory()->published()->create();

    $response = $this->getJson("/api/companies/{$company->slug}/publications");

    expect($response->json('data'))->toHaveCount(2);
});
