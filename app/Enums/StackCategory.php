<?php

namespace App\Enums;

/**
 * Groups the stack cards on the index page.
 */
enum StackCategory: string
{
    case Web = 'web';
    case Api = 'api';
    case Async = 'async';
    case Product = 'product';

    public function label(): string
    {
        return match ($this) {
            self::Web => 'Aplicaciones web',
            self::Api => 'APIs y frontends desacoplados',
            self::Async => 'Segundo plano y tiempo real',
            self::Product => 'Producto y escala',
        };
    }
}
