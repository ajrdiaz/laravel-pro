<?php

namespace App\Enums;

/**
 * How demanding a portfolio project is. The case order is the order they are shown in.
 */
enum ProjectLevel: string
{
    case Basic = 'basico';
    case Intermediate = 'intermedio';
    case Advanced = 'avanzado';

    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Básico',
            self::Intermediate => 'Intermedio',
            self::Advanced => 'Avanzado',
        };
    }
}
