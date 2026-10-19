---
title: Relaciones muchos a muchos, scopes y accessors
summary: Pivotes con attach y sync, scopes para reutilizar condiciones y accessors para transformar atributos.
minutes: 8
---

Con lo básico de Eloquent resuelves un CRUD. Con estas tres herramientas, tu modelo empieza a expresar el lenguaje de tu negocio.

## Muchos a muchos

Un post tiene muchas etiquetas y una etiqueta está en muchos posts. Se necesita una tabla intermedia (el **pivote**), llamada por convención con los dos nombres en singular y orden alfabético: `post_tag`.

```php
Schema::create('post_tag', function (Blueprint $table) {
    $table->foreignId('post_id')->constrained()->cascadeOnDelete();
    $table->foreignId('tag_id')->constrained()->cascadeOnDelete();
    $table->primary(['post_id', 'tag_id']);
});
```

```php
// Post.php
public function tags(): BelongsToMany
{
    return $this->belongsToMany(Tag::class);
}
```

Esta app lo usa igual: la tabla `module_stack` une módulos y stacks, y `Stack::modules()` es un `belongsToMany`.

Para modificar la relación:

```php
$post->tags()->attach($tag);              // añade
$post->tags()->detach($tag);              // quita
$post->tags()->sync([1, 2, 3]);           // deja exactamente estas tres
$post->tags()->toggle([1, 2]);            // añade las que faltan y quita las que están
```

`sync()` es la más útil: recibe la lista final y se encarga de quitar y añadir lo necesario. La usa `StackSeeder` en esta app para dejar los módulos de cada stack exactamente como dice el JSON.

Y el N+1 también ocurre aquí: `Post::with('tags')->get()`.

## Scopes: condiciones con nombre

Si escribes `->whereNotNull('published_at')` en diez sitios, ponle nombre:

```php
use Illuminate\Database\Eloquent\Attributes\Scope;

#[Scope]
protected function published(Builder $query): void
{
    $query->whereNotNull('published_at');
}

Post::published()->latest()->get();
```

El atributo `#[Scope]` marca el método como un scope local, y se encadena como cualquier otra condición. Un scope puede recibir argumentos: `Flashcard::dueFor($user)` en `app/Models/Flashcard.php` filtra las tarjetas que tocan hoy para un usuario.

El valor de un scope no es solo no repetirte: es que `published` **dice qué significa** estar publicado, y si esa regla cambia, cambia en un solo lugar.

## Accessors: transformar al leer

```php
use Illuminate\Database\Eloquent\Casts\Attribute;

protected function title(): Attribute
{
    return Attribute::make(
        get: fn (string $value) => ucfirst($value),
    );
}
```

Ahora `$post->title` devuelve el valor con la primera letra en mayúscula, sin tocar lo que hay en la base de datos. Con `set:` puedes transformar también al escribir, por ejemplo normalizar un email a minúsculas.

Cuando la misma transformación se repite en varios modelos, no copies el accessor: extráela a un **cast personalizado**. El proyecto tiene `app/Casts/PlainDate.php`, que guarda una fecha como `Y-m-d` y la devuelve como un objeto de fecha, y lo usan `FlashcardReview`, `StudyDay` y `Stack`.

## Cuándo no

- No metas lógica de negocio pesada en un accessor: se ejecuta cada vez que lees el atributo.
- Un accessor que hace una consulta a la base de datos esconde un N+1. Si necesita datos de otra tabla, cárgalos explícitamente.
- No uses un scope para algo que solo se usa una vez.

## Errores comunes

- Usar `attach()` repetidamente en lugar de `sync()` y acabar con duplicados.
- Olvidar la clave primaria compuesta en el pivote: permite filas repetidas.
- Un accessor que llama a otro modelo por cada fila.

## Practica

Añade etiquetas a los posts de tu blog del reto: relación muchos a muchos, un scope `published`, y comprueba con `DB::listen` que listar los posts con sus etiquetas sigue haciendo las mismas consultas.
