<?php

use App\Models\User;

it('registers a user and returns a token', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Иван Тестов',
        'login' => 'ivan_test',
        'email' => 'ivan@test.dev',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ]);

    $response->assertCreated()
        ->assertJsonPath('user.login', 'ivan_test')
        ->assertJsonStructure(['user' => ['id', 'login', 'name'], 'token']);

    expect(User::query()->where('login', 'ivan_test')->exists())->toBeTrue();
});

it('rejects duplicate login on registration', function () {
    User::factory()->create(['login' => 'taken']);

    $this->postJson('/api/auth/register', [
        'name' => 'Клон',
        'login' => 'taken',
        'email' => 'clone@test.dev',
        'password' => 'secret1234',
        'password_confirmation' => 'secret1234',
    ])->assertUnprocessable()->assertJsonValidationErrors('login');
});

it('logs in with email or login', function () {
    $user = User::factory()->create(['password' => 'secret1234']);

    $byLogin = $this->postJson('/api/auth/login', ['login' => $user->login, 'password' => 'secret1234']);
    $byEmail = $this->postJson('/api/auth/login', ['login' => $user->email, 'password' => 'secret1234']);

    $byLogin->assertOk()->assertJsonStructure(['token']);
    $byEmail->assertOk()->assertJsonStructure(['token']);
});

it('rejects wrong credentials', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/login', ['login' => $user->email, 'password' => 'wrong-password'])
        ->assertUnauthorized();
});

it('returns authenticated user for me endpoint', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->getJson('/api/me')
        ->assertOk()
        ->assertJsonPath('data.login', $user->login);
});

it('revokes token on logout', function () {
    $user = User::factory()->create();
    $token = $user->createToken('api')->plainTextToken;

    $this->withToken($token)->postJson('/api/auth/logout')->assertOk();

    expect($user->tokens()->count())->toBe(0);

    // RequestGuard caches the resolved user for the whole app lifetime,
    // so guards must be reset to make the next request re-check the token.
    $this->app->make('auth')->forgetGuards();

    $this->withToken($token)->getJson('/api/me')->assertUnauthorized();
});

it('updates profile and persists feed settings', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/profile', [
            'name' => 'Новое имя',
            'about' => 'Backend-разработчик',
            'location' => 'Тбилиси, Грузия',
            'feed_settings' => [
                'types' => ['article'],
                'difficulties' => ['medium', 'hard'],
                'min_rating' => 25,
            ],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Новое имя')
        ->assertJsonPath('data.location', 'Тбилиси, Грузия');

    expect($user->refresh()->feed_settings)->toEqual([
        'types' => ['article'],
        'difficulties' => ['medium', 'hard'],
        'min_rating' => 25,
    ]);
});

it('validates feed settings on profile update', function () {
    $user = User::factory()->create();

    $this->actingAs($user, 'sanctum')
        ->putJson('/api/profile', [
            'feed_settings' => [
                'types' => ['poem'],
                'min_rating' => -5,
            ],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['feed_settings.types.0', 'feed_settings.min_rating']);
});

it('requires authentication for profile update', function () {
    $this->putJson('/api/profile', ['name' => 'Злоумышленник'])->assertUnauthorized();
});
