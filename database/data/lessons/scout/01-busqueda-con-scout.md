---
title: Búsqueda con Scout, de LIKE a un índice de verdad
summary: Indexar modelos con Scout, elegir un motor, mantener el índice sincronizado y cuándo basta con la base de datos.
minutes: 8
---

`where('title', 'like', '%laravel%')` funciona hasta que tienes cien mil filas, o hasta que alguien escribe «larvel» con una errata. Un motor de búsqueda se encarga de eso.

## Primero: ¿de verdad lo necesitas?

Scout trae un motor `database` que busca sobre tus propias tablas, sin servicios extra. Para empezar, o para un catálogo pequeño, suele bastar. Pasa a un motor dedicado cuando necesites tolerancia a errores de tipeo, relevancia, filtros rápidos o miles de registros.

## Convertir un modelo en buscable

```bash
composer require laravel/scout
php artisan vendor:publish --provider="Laravel\Scout\ScoutServiceProvider"
```

```php
use Laravel\Scout\Searchable;

class Post extends Model
{
    use Searchable;

    /** @return array<string, mixed> */
    public function toSearchableArray(): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'body' => $this->body,
        ];
    }
}
```

Con el trait, crear, actualizar o borrar un `Post` actualiza el índice. `toSearchableArray()` decide qué datos se envían: indexa solo lo que se busca o se filtra, no el modelo entero.

```php
Post::search('laravel')->get();
Post::search('laravel')->where('category_id', 3)->paginate(15);
```

## Elegir el motor

El motor se elige con la variable `SCOUT_DRIVER`. Scout soporta Algolia, Meilisearch, Typesense y Turbopuffer, además del motor `database` y del motor `collection`, este último útil en los tests porque no necesita ningún servicio.

| Motor | Cuándo |
| --- | --- |
| `database` | Empezar sin servicios, catálogos pequeños |
| Meilisearch o Typesense | Búsqueda tolerante a errores, autoalojada o gestionada |
| Algolia | Servicio gestionado de pago |

## El índice es una copia

La base de datos es la **fuente de verdad**. El índice se puede perder, quedar desfasado o necesitar un esquema nuevo, así que debes poder reconstruirlo:

```bash
php artisan scout:flush "App\Models\Post"
php artisan scout:import "App\Models\Post"
```

`scout:flush` vacía el índice del modelo y `scout:import` lo vuelve a llenar con lo que hay en la base de datos. Hazlo parte de tu proceso de despliegue cuando cambies lo que se indexa.

## Indexar en cola

Por defecto, guardar un modelo espera a que el motor de búsqueda responda. Si ese servicio va lento o está caído, el usuario lo nota. Activa la cola en `config/scout.php`:

```php
'queue' => true,
```

Así guardar es rápido y un worker actualiza el índice después. A cambio, el índice puede ir unos segundos por detrás.

## No indexar todo

```php
public function shouldBeSearchable(): bool
{
    return $this->isPublished();
}
```

Un borrador no debería aparecer en la búsqueda pública. `shouldBeSearchable()` decide qué registros entran al índice.

## Búsqueda semántica

Laravel 13 añade consultas por similitud de vectores sobre PostgreSQL con `pgvector`, y el SDK de IA genera los embeddings. Sirve para buscar por **significado** («cómo cancelo mi plan») y no por palabras exactas. Es un paso más avanzado; mira la documentación de búsqueda cuando lo necesites.

## Errores comunes

- Montar Meilisearch para un catálogo de doscientos productos.
- No tener un plan de reindexado y descubrir que el índice se desfasó.
- Indexar el modelo entero, con datos privados, en un servicio externo.
- Probar la búsqueda contra el servicio real en cada test.

## Practica

Haz el reto de este módulo: añade búsqueda a un catálogo con Scout y Meilisearch (o el motor `database`), con indexación en cola, un filtro por categoría y un test que use el motor `collection`.
