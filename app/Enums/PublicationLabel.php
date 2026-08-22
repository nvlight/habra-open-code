<?php

namespace App\Enums;

enum PublicationLabel: string
{
    case Tutorial = 'tutorial';
    case CaseStudy = 'case';
    case Analytics = 'analytics';
    case Opinion = 'opinion';
    case Review = 'review';
    case Digest = 'digest';
    case Retrospective = 'retrospective';
    case Roadmap = 'roadmap';

    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            self::Tutorial->value => 'Туториал',
            self::CaseStudy->value => 'Кейс',
            self::Analytics->value => 'Аналитика',
            self::Opinion->value => 'Мнение',
            self::Review->value => 'Обзор',
            self::Digest->value => 'Дайджест',
            self::Retrospective->value => 'Ретроспектива',
            self::Roadmap->value => 'Роадмэп',
        ];
    }

    public function label(): string
    {
        return self::labels()[$this->value];
    }
}
