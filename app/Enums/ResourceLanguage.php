<?php

namespace App\Enums;

enum ResourceLanguage: string
{
    case Es = 'es';
    case En = 'en';

    public function label(): string
    {
        return match ($this) {
            self::Es => 'Español',
            self::En => 'Inglés',
        };
    }
}
