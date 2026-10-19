<?php

namespace App\Enums;

/**
 * The layers a stack is described by. The case order is the order they are shown in.
 */
enum StackLayer: string
{
    case Backend = 'backend';
    case Frontend = 'frontend';
    case Database = 'database';
    case Auth = 'auth';
    case Api = 'api';
    case Queue = 'queue';
    case Cache = 'cache';
    case Search = 'search';
    case Realtime = 'realtime';
    case Admin = 'admin';
    case Payments = 'payments';
    case Testing = 'testing';
    case Deploy = 'deploy';
    case Monitoring = 'monitoring';

    public function label(): string
    {
        return match ($this) {
            self::Backend => 'Backend',
            self::Frontend => 'Frontend',
            self::Database => 'Base de datos',
            self::Auth => 'Autenticación',
            self::Api => 'API',
            self::Queue => 'Colas y workers',
            self::Cache => 'Caché',
            self::Search => 'Búsqueda',
            self::Realtime => 'Tiempo real',
            self::Admin => 'Panel de administración',
            self::Payments => 'Pagos',
            self::Testing => 'Testing',
            self::Deploy => 'Despliegue',
            self::Monitoring => 'Monitoreo',
        };
    }
}
