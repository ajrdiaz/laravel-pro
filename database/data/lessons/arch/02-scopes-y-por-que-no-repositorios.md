---
title: Consultas con nombre: scopes, y por qué casi nunca necesitas repositorios
summary: Cómo dar nombre a una consulta que se repite con un scope de Eloquent, cuándo un global scope es buena idea y por qué envolver Eloquent en un repositorio suele sobrar.
minutes: 8
---

Casi todas las aplicaciones acaban con la misma consulta copiada en cuatro controladores: «los pedidos pagados de este mes», «las tarjetas que tocan hoy». Cuando cambia la regla, se cambia en tres sitios y se olvida el cuarto. Eloquent ya trae la herramienta para esto.

## Scope local: una consulta con nombre

Esta app tiene uno real en el modelo `Flashcard`: las tarjetas que le tocan a un usuario hoy.

```php
use Illuminate\Database\Eloquent\Attributes\Scope;

#[Scope]
protected function dueFor(Builder $query, User $user): void
{
    $query->whereDoesntHave('reviews', fn (Builder $reviews) => $reviews
        ->whereBelongsTo($user)
        ->where('due_on', '>', today()->toDateString()));
}
```

Se llama como si fuera un método del modelo, y se encadena con lo demás:

```php
Flashcard::dueFor($user)->count();                       // las estadísticas
Flashcard::dueFor($user)->where('module_id', 3)->get();  // la cola de un módulo
```

El SQL resultante deja ver el valor del scope: una condición bastante fea que **escribes una sola vez**.

```sql
select * from "flashcards" where not exists (
    select * from "flashcard_reviews"
    where "flashcards"."id" = "flashcard_reviews"."flashcard_id"
      and "flashcard_reviews"."user_id" in (7)
      and "due_on" > '2026-10-19'
) and "module_id" = 3
```

Las reglas de la documentación: se marca el método con `#[Scope]`, se recibe el `Builder` y se **devuelve `void` o el mismo builder**. Los parámetros extra van después del `$query`.

## orWhere con scopes: cuidado con los paréntesis

Combinar scopes con `orWhere` puede agrupar mal las condiciones. Laravel ofrece un `orWhere` de orden superior que lo evita:

```php
User::popular()->orWhere->active()->get();
```

Si te lías, imprime el SQL con `toRawSql()` y mira dónde quedaron los paréntesis.

## withAttributes: el scope que también crea

Un scope puede fijar atributos, y esos atributos se aplican tanto al filtro como a los modelos que crees a través de él:

```php
#[Scope]
protected function draft(Builder $query): void
{
    $query->withAttributes(['hidden' => true]);
}

$post = Post::draft()->create(['title' => 'En curso']);
$post->hidden; // true
```

Con `asConditions: false` solo asigna los atributos y no filtra.

## Global scopes: úsalos con mucho cuidado

Un global scope añade una condición a **todas** las consultas del modelo. Así funcionan los *soft deletes*: cada consulta ignora los borrados sin que lo pidas. Se aplica con `#[ScopedBy([AncientScope::class])]` y se quita con `withoutGlobalScope(...)`.

La regla práctica: un global scope solo para una condición que **nunca** debes olvidar (borrados lógicos, el inquilino de una app multi-tenant). Para todo lo demás usa un scope local: una condición invisible en todas las consultas es una fuente de «¿por qué no aparece este registro?».

## ¿Y los repositorios?

Un repositorio envuelve Eloquent detrás de una interfaz: `OrderRepository::paidThisMonth()`. En Laravel casi nunca compensa:

- Eloquent **ya es** una capa de acceso a datos. Un repositorio que reexpone `find`, `create` y `where` copia su API sin añadir nada.
- Cambiar de motor de base de datos, la razón clásica, lo cubre en gran parte la configuración de Laravel. Las pruebas corren con una base de datos real en memoria.
- Cada método nuevo hay que escribirlo dos veces: en el repositorio y en su interfaz.

Esta app no tiene ni uno. Cada tipo de lógica tiene su sitio:

| Qué es | Dónde va | Ejemplo en esta app |
| --- | --- | --- |
| Una condición reutilizable de un modelo | Scope local | `Flashcard::dueFor()` |
| Una consulta que cruza modelos o arma un informe | Servicio | `ProgressReport` |
| Una escritura con reglas de negocio | Acción | `ToggleCompletion` |

Un repositorio empieza a tener sentido cuando de verdad hay **dos orígenes de datos** (por ejemplo, una API externa y la base de datos) detrás de la misma interfaz. Hasta entonces, es una capa más que mantener.

## Probar un scope

Un scope se prueba con datos reales, que es más rápido y más fiable que simularlo:

```php
public function test_due_cards_exclude_those_scheduled_for_later()
{
    $user = User::factory()->create();
    $due = Flashcard::factory()->create();
    $later = Flashcard::factory()->create();

    FlashcardReview::create([
        'user_id' => $user->id,
        'flashcard_id' => $later->id,
        'box' => 2,
        'due_on' => today()->addDays(3),
    ]);

    $this->assertSame([$due->id], Flashcard::dueFor($user)->pluck('id')->all());
}
```

## Errores comunes

- Copiar la misma condición en varios controladores en vez de darle nombre.
- Un scope que hace trabajo de servicio: cruza cuatro tablas y devuelve un arreglo. Eso es un servicio.
- Un global scope para algo opcional: acabarás usando `withoutGlobalScope` en la mitad de las consultas.
- Un repositorio que solo reenvía llamadas a Eloquent.
- Combinar scopes con `orWhere` sin revisar el SQL.

## Practica

Busca en tu código una condición `where` repetida en dos o más sitios y conviértela en un scope local con un nombre que diga qué significa, no cómo se calcula. Imprime el SQL con `toRawSql()` antes y después, y escribe un test que lo cubra con datos reales.
