<?php

namespace Database\Seeders;

use App\Models\Badge;
use Illuminate\Database\Seeder;

class BadgeSeeder extends Seeder
{
    public function run(): void
    {
        $badges = [
            ['name' => 'Захабренный', 'slug' => 'habred', 'description' => 'Публикация попала на главную страницу'],
            ['name' => 'Легенда', 'slug' => 'legend', 'description' => 'Легенда сообщества'],
            ['name' => 'Комментатор', 'slug' => 'commenter', 'description' => 'Активный участник дискуссий'],
            ['name' => 'Старожил', 'slug' => 'veteran', 'description' => 'Более десяти лет на Хабре'],
            ['name' => 'Переводчик', 'slug' => 'translator', 'description' => 'Переводит качественные материалы'],
            ['name' => 'Журналист', 'slug' => 'journalist', 'description' => 'Пишет новостные публикации'],
            ['name' => 'Из редакции', 'slug' => 'editorial', 'description' => 'Сотрудник редакции Хабра'],
        ];

        foreach ($badges as $badge) {
            Badge::query()->updateOrCreate(['slug' => $badge['slug']], $badge);
        }
    }
}
