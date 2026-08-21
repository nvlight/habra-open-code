<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call([
            BadgeSeeder::class,
            IndustrySeeder::class,
            HubSeeder::class,
            UserSeeder::class,
            CompanySeeder::class,
            PublicationSeeder::class,
            InteractionSeeder::class,
        ]);
    }
}
