<?php

namespace App\Enums;

enum Difficulty: string
{
    case Easy = 'easy';
    case Medium = 'medium';
    case Hard = 'hard';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Easy->value => 'Простой',
            self::Medium->value => 'Средний',
            self::Hard->value => 'Сложный',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
