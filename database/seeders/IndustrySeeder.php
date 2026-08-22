<?php

namespace Database\Seeders;

use App\Models\Industry;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class IndustrySeeder extends Seeder
{
    public function run(): void
    {
        $industries = [
            'Программное обеспечение',
            'Связь и телекоммуникации',
            'Домены и хостинг',
            'Финтех',
            'E-commerce',
            'Искусственный интеллект',
            'Кибербезопасность',
            'Разработка игр',
            'Образование',
            'Консалтинг и аутсорс',
        ];

        foreach ($industries as $name) {
            Industry::query()->updateOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name]
            );
        }
    }
}
