<?php

namespace Database\Seeders;

use App\Models\Company;
use App\Models\Industry;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CompanySeeder extends Seeder
{
    public function run(): void
    {
        $companies = [
            ['name' => 'Timeweb Cloud', 'slug' => 'timeweb', 'size' => '201–500', 'founded_at' => '2022-02-09'],
            ['name' => 'AvitoTech', 'slug' => 'avito', 'size' => '1001–5000', 'founded_at' => '2007-10-02'],
            ['name' => 'RUVDS.com', 'slug' => 'ruvds', 'size' => '201–500', 'founded_at' => '2011-05-12'],
            ['name' => 'Selectel', 'slug' => 'selectel', 'size' => '501–1000', 'founded_at' => '2001-06-01'],
            ['name' => 'OTUS', 'slug' => 'otus', 'size' => '101–200', 'founded_at' => '2015-03-15'],
            ['name' => 'Альфа-Банк', 'slug' => 'alfa', 'size' => '5000+', 'founded_at' => '1990-01-01'],
            ['name' => 'Cloud.ru', 'slug' => 'cloud_ru', 'size' => '501–1000', 'founded_at' => '2012-11-20'],
            ['name' => 'Яндекс Практикум', 'slug' => 'yandex_praktikum', 'size' => '501–1000', 'founded_at' => '2019-04-01'],
        ];

        foreach ($companies as $data) {
            /** @var Company $company */
            $company = Company::query()->updateOrCreate(
                ['slug' => $data['slug']],
                [
                    ...collect($data)->except('slug')->all(),
                    'description' => fake()->paragraph(3),
                    'website' => 'https://'.Str::slug($data['name']).'.example.com',
                    'rating' => fake()->randomFloat(2, 300, 1500),
                    'location' => fake()->randomElement(['Россия', 'Санкт-Петербург, Россия']),
                ]
            );

            $representativeLogin = Str::studly($data['slug']).'_team';

            /** @var User $representative */
            $representative = User::query()->firstOrCreate(
                ['login' => $representativeLogin],
                [
                    'name' => $data['name'],
                    'about' => "Официальный блог компании {$data['name']}.",
                    'karma' => 100,
                    'rating' => fake()->randomFloat(2, 100, 900),
                    'email' => Str::lower($representativeLogin).'@habr.test',
                    'password' => Hash::make('password'),
                ]
            );

            $company->update(['representative_id' => $representative->id]);
            $representative->update(['company_id' => $company->id]);
            $company->employees()->syncWithoutDetaching([
                $representative->id => ['role' => 'Представитель компании'],
            ]);

            $industries = Industry::query()
                ->inRandomOrder()
                ->limit(fake()->numberBetween(1, 3))
                ->pluck('id');

            $company->industries()->syncWithoutDetaching($industries);
        }
    }
}
