---
title: Filtros, orden y paginación en una API
summary: Aceptar parámetros de consulta de forma segura, con listas permitidas, paginación y sin abrir la puerta a un N+1.
minutes: 8
---

Un `GET /api/tasks` que devuelve todo funciona el primer día. Al mes, alguien pide filtrar por estado, ordenar por fecha y paginar. Esta lección lo resuelve sin abrir agujeros.

## Nunca confíes en el parámetro

El error clásico:

```php
// MAL: ordena por la columna que diga el cliente
$query->orderBy($request->query('sort'));
```

Con `?sort=password` ordenarías por una columna que no debería ser pública, y con un valor malicioso podrías provocar errores o fugas. La regla: **una lista de valores permitidos**.

## Validar los parámetros

```php
public function index(Request $request): AnonymousResourceCollection
{
    $validated = $request->validate([
        'status' => ['nullable', Rule::in(['open', 'done'])],
        'sort' => ['nullable', Rule::in(['created_at', '-created_at', 'title'])],
        'per_page' => ['nullable', 'integer', 'between:1,100'],
    ]);

    $sort = $validated['sort'] ?? '-created_at';

    $tasks = $request->user()->tasks()
        ->with('owner')
        ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
        ->orderBy(ltrim($sort, '-'), str_starts_with($sort, '-') ? 'desc' : 'asc')
        ->paginate($validated['per_page'] ?? 15)
        ->withQueryString();

    return TaskResource::collection($tasks);
}
```

Cada parte tiene una razón:

- `Rule::in([...])` es la **lista permitida**: un `sort` desconocido da un 422, no una consulta rara.
- `between:1,100` pone un **tope** a `per_page`. Sin él, alguien pide un millón de filas.
- `when(...)` añade la condición solo si el parámetro vino, y mantiene la consulta legible.
- El prefijo `-` para el orden descendente es una convención muy extendida en APIs.
- `with('owner')` evita el N+1 que provocaría el resource al mostrar el dueño de cada tarea.
- `withQueryString()` conserva `status` y `sort` en los enlaces de la siguiente página.

El filtrado queda siempre dentro de `$request->user()->tasks()`: un usuario nunca ve las tareas de otro por mucho que juegue con los parámetros.

## Paginar: dos opciones

| Método | Cuándo |
| --- | --- |
| `paginate()` | Listados con «página 3 de 12» y saltos a una página concreta |
| `cursorPaginate()` | Tablas enormes o scroll infinito |

`paginate()` hace una consulta extra para contar el total, que en tablas grandes es lenta. `cursorPaginate()` no cuenta: solo sabe qué viene después, y es mucho más rápida y estable si los datos cambian mientras se navega, a cambio de no poder saltar a una página arbitraria. Necesita un orden por una columna única.

Con un resource, la respuesta incluye `data`, `links` y `meta` automáticamente.

## La respuesta que el cliente espera

```json
{
  "data": [{ "id": 1, "title": "Aprender Pest", "done": false }],
  "links": { "next": "https://app.test/api/tasks?page=2" },
  "meta": { "current_page": 1, "per_page": 15, "total": 42 }
}
```

Documenta qué filtros acepta cada endpoint; Scramble o Scribe pueden leerlo de tu `validate()`.

## Probarlo

```php
it('filtra por estado y rechaza un orden desconocido', function () {
    $user = User::factory()->has(Task::factory()->count(3))->create();
    Sanctum::actingAs($user);

    $this->getJson('/api/tasks?status=done')->assertOk();
    $this->getJson('/api/tasks?sort=password')->assertUnprocessable();
});
```

## Errores comunes

- Pasar `sort`, `filter` o `per_page` directo a la consulta.
- Sin tope para `per_page`.
- Olvidar `with()` y que cada fila dispare una consulta extra.
- Un listado sin paginación «porque ahora son pocos».

## Practica

Añade al listado de tu API de tareas filtro por estado, orden con lista permitida y `per_page` con tope, y escribe un test por cada caso inválido.
