<?php

namespace App\Enums;

enum PublicationType: string
{
    case Article = 'article';
    case Post = 'post';
    case News = 'news';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Article->value => 'Статья',
            self::Post->value => 'Пост',
            self::News->value => 'Новость',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
