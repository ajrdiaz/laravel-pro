---
title: Eloquent sin N+1
summary: Relaciones, eager loading y cómo cazar las consultas de más antes de que lleguen a producción.
minutes: 8
---

Eloquent hace tan fácil leer datos que es fácil leer demasiados. El error más caro y más común es el **N+1**.

## El problema

```php
$posts = Post::all();                  // 1 consulta

foreach ($posts as $post) {
    echo $post->author->name;          // 1 consulta MÁS por cada post
}
```

Con 100 posts son 101 consultas. En local no lo notas; en producción, sí.

## La solución: eager loading

```php
$posts = Post::with('author')->get();  // 2 consultas, sin importar cuántos posts haya
```

- `with()` carga la relación junto con la consulta inicial. `load()` lo hace después, sobre modelos que ya tienes.
- `withCount('comments')` añade el atributo `comments_count` sin traer los comentarios.
- Carga solo las columnas que vas a usar: `with('author:id,name')`. Incluye siempre la clave que une la relación.

## Cazarlo en desarrollo

```php
// AppServiceProvider::boot()
Model::preventLazyLoading(! app()->isProduction());
```

Con esto, acceder a una relación sin haberla cargado lanza una excepción en local y en los tests. En producción no rompe nada. Así el N+1 se descubre al escribirlo, no cuando alguien se queja de que la página tarda.

## Filtrar por la relación

```php
User::whereHas('orders', fn ($query) => $query->where('status', 'paid'))->get();
```

`with()` solo carga datos. Para quedarte con los usuarios según lo que pasa en su relación, usa `whereHas()`.

## Asignación masiva

Decide qué campos pueden rellenarse con `create()` o `update()`. En esta app lo verás con el atributo `#[Fillable]` encima de cada modelo, por ejemplo en `app/Models/Concept.php`. Es otra forma de expresar lo mismo que la propiedad `$fillable`.

## Lotes grandes

```php
Product::query()->chunkById(500, function ($products) {
    foreach ($products as $product) {
        // ...
    }
});
```

`chunkById` pagina por id, así que no se salta filas aunque las modifiques mientras recorres.

## Errores comunes

- Llamar a `$post->comments->count()` dentro de un bucle. Usa `withCount('comments')`.
- Usar `whereHas` sobre una clave foránea sin índice: la consulta se vuelve lenta.
- Cargar relaciones dentro de un API Resource sin `whenLoaded()`: es un N+1 escondido.

## Practica

Haz el reto de este módulo: un blog con usuarios, posts, tags y comentarios, listando todo con 4 consultas como máximo. Compruébalo con `DB::listen` o con Debugbar.
