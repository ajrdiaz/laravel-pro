<?php

namespace App\Enums;

/**
 * Types of learning resource. The case order is the order the groups are shown in.
 */
enum ResourceKind: string
{
    case Docs = 'docs';
    case Course = 'course';
    case Video = 'video';
    case Article = 'article';
    case Book = 'book';
    case Tool = 'tool';

    public function label(): string
    {
        return match ($this) {
            self::Docs => 'Documentación',
            self::Course => 'Cursos y rutas',
            self::Video => 'Videos',
            self::Article => 'Artículos y guías',
            self::Book => 'Libros',
            self::Tool => 'Herramientas y servicios',
        };
    }
}
