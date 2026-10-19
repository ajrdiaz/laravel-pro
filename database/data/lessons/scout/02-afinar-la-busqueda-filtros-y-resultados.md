---
title: Afinar la búsqueda: filtros, ajustes del índice y resultados
summary: Cómo filtrar y ordenar con Scout, qué hay que declarar antes en Meilisearch, cómo evitar consultas de más al mostrar los resultados y cómo hacer operaciones masivas sin saturar el índice.
minutes: 8
---

Con Scout funcionando, lo siguiente suele ser que el equipo pida lo de siempre: filtrar por categoría, ordenar por precio, paginar y que no vaya lenta. Todo eso tiene un sitio en Scout, y casi siempre una trampa.

## Filtrar: where, whereIn y operadores

Un buscador devuelve registros de **todo el mundo** si no le dices lo contrario. La primera regla es acotar por dueño:

```php
Order::search('Star Trek')->where('user_id', $request->user()->id)->get();
```

Scout admite igualdad y los operadores `=`, `!=`, `<`, `>`, `>=` y `<=`, además de `whereIn` y `whereNotIn`:

```php
Order::search('Star Trek')
    ->where('status', '=', 'completed')
    ->where('total_price', '>', 100)
    ->whereIn('category', ['dvd', 'streaming'])
    ->whereNotIn('region', ['restricted'])
    ->get();
```

Fíjate en que filtras sobre **lo que está en el índice**: si el campo no está en `toSearchableArray()`, no existe para el motor.

## Meilisearch: declara qué se puede filtrar y ordenar

Meilisearch exige definir los ajustes del índice **antes** de filtrar u ordenar. Los atributos filtrables son los que usarás con `where`, y los ordenables, los que usarás con `orderBy`. Se configuran en `config/scout.php`:

```php
'meilisearch' => [
    'host' => env('MEILISEARCH_HOST', 'http://localhost:7700'),
    'key' => env('MEILISEARCH_KEY'),
    'index-settings' => [
        Order::class => [
            'filterableAttributes' => ['id', 'user_id', 'status', 'category'],
            'sortableAttributes' => ['created_at'],
        ],
    ],
],
```

Después de cambiar estos ajustes hay que enviarlos al motor, y la documentación recomienda hacerlo parte del despliegue:

```bash
php artisan scout:sync-index-settings
```

Si olvidas declarar un atributo, el motor rechaza el filtro: es el error más común con Meilisearch.

## Ordenar y paginar

```php
Order::search('Star Trek')->orderBy('created_at', 'desc')->paginate(15);
```

`paginate` devuelve un paginador con el **total** de resultados, igual que el de Eloquent, y se serializa a Inertia o a JSON de la misma forma. Con el motor `database`, `simplePaginate` evita calcular el total y solo sabe si hay una página más, que basta para enlaces de «anterior» y «siguiente»:

```php
Order::search('Star Trek')->simplePaginate(15);
```

## Mostrar resultados sin consultas de más

Scout le pregunta al motor qué registros coinciden y luego carga los modelos con Eloquent por su clave primaria. Esa segunda consulta es tuya, y puedes ajustarla con `query()`; sin ello, mostrar el autor de cada resultado es un N+1 clásico:

```php
use Illuminate\Database\Eloquent\Builder;

$orders = Order::search('Star Trek')
    ->query(fn (Builder $query) => $query->with('invoices'))
    ->get();
```

Para la **importación masiva** hay un gancho aparte: `makeAllSearchableUsing`, donde añades las relaciones que necesites antes de indexar:

```php
protected function makeAllSearchableUsing(Builder $query): Builder
{
    return $query->with('author');
}
```

La documentación avisa de que puede no aplicarse cuando importas en lotes por cola, porque las relaciones no se restauran al deserializar los modelos.

## El motor database: cómo busca cada columna

Con el motor `database`, por defecto cada columna se busca con `LIKE`. Puedes elegir una estrategia distinta por columna con atributos sobre `toSearchableArray`:

```php
use Laravel\Scout\Attributes\SearchUsingFullText;
use Laravel\Scout\Attributes\SearchUsingPrefix;

#[SearchUsingPrefix(['id', 'email'])]
#[SearchUsingFullText(['bio'])]
public function toSearchableArray(): array
{
    return [
        'id' => $this->id,
        'email' => $this->email,
        'bio' => $this->bio,
    ];
}
```

`SearchUsingFullText` usa el índice de texto completo de tu base de datos, y `SearchUsingPrefix` solo busca al principio de la cadena (`ejemplo%`) en vez de en cualquier parte (`%ejemplo%`), lo que puede aprovechar un índice normal. Las columnas sin atributo siguen con `LIKE`. Antes de marcar una columna con `SearchUsingFullText`, comprueba que tiene un **índice de texto completo**; sin él, la consulta no puede usarlo.

## Operaciones masivas sin saturar el índice

Cada cambio en un modelo buscable actualiza el índice. Si vas a tocar miles de registros y no quieres enviar cada cambio, hazlo dentro de `withoutSyncingToSearch` y reindexa después:

```php
Order::withoutSyncingToSearch(function () {
    Order::where('status', 'draft')->update(['archived' => true]);
});
```

Y para indexar o quitar del índice un conjunto concreto, usa `searchable()` y `unsearchable()` sobre una consulta:

```php
Order::where('price', '>', 100)->searchable();
Order::where('price', '>', 100)->unsearchable();
```

## Probar la búsqueda

Con `SCOUT_DRIVER=collection` en `phpunit.xml`, las pruebas no necesitan ningún servicio:

```php
public function test_it_finds_an_order_by_title()
{
    Order::factory()->create(['title' => 'Star Trek']);
    Order::factory()->create(['title' => 'Otra cosa']);

    $this->assertCount(1, Order::search('Star Trek')->get());
}
```

Prueba también el filtro por dueño: crea un pedido de otra persona con el mismo título y comprueba que **no** aparece.

## Errores comunes

- Una búsqueda sin filtrar por dueño: devuelve registros de otras personas.
- En Meilisearch, usar `where` u `orderBy` sobre un atributo que no declaraste en `index-settings`.
- Olvidar `scout:sync-index-settings` tras cambiar los ajustes.
- Mostrar relaciones de los resultados sin `query()`: N+1.
- Reindexar miles de registros uno a uno en vez de en bloque.

## Practica

Añade a tu catálogo un filtro por categoría y un orden por fecha. Declara los atributos en `index-settings` y comprueba qué pasa si quitas uno. Escribe una prueba que cree datos de dos usuarios con el mismo texto y verifique que cada uno solo encuentra los suyos, y mide con `DB::listen` cuántas consultas lanza la página de resultados con y sin `query(fn ... with(...))`.
