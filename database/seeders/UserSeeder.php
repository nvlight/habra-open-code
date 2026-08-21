<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class UserSeeder extends Seeder
{
    public function run(): void
    {
        /** @var User $admin */
        $admin = User::query()->updateOrCreate(
            ['login' => 'admin'],
            [
                'name' => 'Админ Хабра',
                'about' => 'Слежу за порядком на Хабре.',
                'karma' => 999,
                'rating' => 500.0,
                'location' => 'Москва, Россия',
                'email' => 'admin@habr.test',
                'password' => Hash::make('password'),
            ]
        );

        $logins = [
            'SLY_G', 'mgebrov', 'fixin', 'dixmod', 'ewolf',
            'Geronom', 'max31ru12', 'altrr', 'Yoker', 'slupoke',
            'Ingir_Max', 'badcasedaily1', 'Cloud4U', 'larissaorehanova', 'mngerasimenko',
        ];

        $previous = $admin;

        foreach ($logins as $i => $login) {
            /** @var User $user */
            $user = User::query()->firstOrCreate(
                ['login' => $login],
                [
                    'name' => fake()->name(),
                    'about' => fake()->optional(0.7)->paragraph(1),
                    'avatar' => null,
                    'karma' => fake()->numberBetween(-20, 1200),
                    'rating' => fake()->randomFloat(2, 10, 800),
                    'location' => fake()->optional()->country(),
                    'invited_by' => $i === 0 ? $admin->id : $previous->id,
                    'email' => Str::lower($login).'@habr.test',
                    'password' => Hash::make('password'),
                ]
            );

            $previous = $user;
        }
    }
}
