<?php

namespace Database\Seeders;

use App\Models\Hub;
use Illuminate\Database\Seeder;

class HubSeeder extends Seeder
{
    public function run(): void
    {
        $hubs = [
            ['name' => 'Программирование', 'alias' => 'programming', 'description' => 'Искусство создания компьютерных программ'],
            ['name' => 'Веб-разработка', 'alias' => 'webdev', 'description' => 'Разработка сайтов и веб-приложений'],
            ['name' => 'JavaScript', 'alias' => 'javascript', 'description' => 'Всё о языке JavaScript'],
            ['name' => 'Python', 'alias' => 'python', 'description' => 'Высокий уровень читаемости кода'],
            ['name' => 'PHP', 'alias' => 'php', 'description' => 'Гипертекстовый препроцессор'],
            ['name' => 'Go', 'alias' => 'go', 'description' => 'Компилируемый многопоточный язык'],
            ['name' => 'Django', 'alias' => 'django', 'description' => 'Python-фреймворк для веба'],
            ['name' => 'PostgreSQL', 'alias' => 'postgresql', 'description' => 'Объектно-реляционная СУБД'],
            ['name' => 'Искусственный интеллект', 'alias' => 'artificial_intelligence', 'description' => 'Машинное обучение, нейросети и ИИ'],
            ['name' => 'Информационная безопасность', 'alias' => 'infosecurity', 'description' => 'Защита информации'],
            ['name' => 'Системное администрирование', 'alias' => 'sys_admin', 'description' => 'Администрирование серверов и сетей'],
            ['name' => 'DevOps', 'alias' => 'devops', 'description' => 'CI/CD, контейнеры, инфраструктура'],
            ['name' => 'Kubernetes', 'alias' => 'kubernetes', 'description' => 'Оркестрация контейнеров'],
            ['name' => 'Микросервисы', 'alias' => 'microservices', 'description' => 'Архитектура распределённых систем'],
            ['name' => 'Анализ и проектирование систем', 'alias' => 'analysis_design', 'description' => 'Проектирование архитектуры'],
            ['name' => 'Алгоритмы', 'alias' => 'algorithms', 'description' => 'Теория алгоритмов и структуры данных'],
            ['name' => 'Разработка мобильных приложений', 'alias' => 'mobile_dev', 'description' => 'iOS и Android разработка'],
            ['name' => 'Разработка игр', 'alias' => 'gamedev', 'description' => 'Создание компьютерных игр'],
            ['name' => 'Дизайн игр', 'alias' => 'game_design', 'description' => 'Геймдизайн и игровые механики'],
            ['name' => 'Облачные сервисы', 'alias' => 'cloud_services', 'description' => 'Cloud computing'],
            ['name' => 'Высоконагруженные системы', 'alias' => 'hi', 'description' => 'Производительность и масштабирование'],
            ['name' => 'Open source', 'alias' => 'open_source', 'description' => 'Открытое программное обеспечение'],
            ['name' => 'Карьера в IT-индустрии', 'alias' => 'career', 'description' => 'Карьерные вопросы в ИТ'],
            ['name' => 'Научно-популярное', 'alias' => 'popular_science', 'description' => 'Наука простыми словами'],
        ];

        foreach ($hubs as $hub) {
            Hub::query()->updateOrCreate(
                ['alias' => $hub['alias']],
                [
                    ...$hub,
                    'rating' => fake()->randomFloat(2, 50, 1500),
                    'subscribers_count' => 0,
                ]
            );
        }
    }
}
