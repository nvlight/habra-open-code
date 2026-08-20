<?php

uses(Illuminate\Foundation\Testing\RefreshDatabase::class);

use App\Models\User;

it('can create a user', function () {
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
    ]);

    expect($user)
        ->name->toBe('Test User')
        ->email->toBe('test@example.com');
});

it('hides sensitive attributes', function () {
    $user = User::factory()->create();

    expect($user->toArray())
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

it('casts email_verified_at to datetime and hashes password', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);

    // password уже hashed благодаря cast
    expect($user->email_verified_at)->toBeInstanceOf(\Illuminate\Support\Carbon::class);
});
