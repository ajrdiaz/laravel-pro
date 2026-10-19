---
title: Paginar y recorrer tablas grandes
summary: Cuándo usar paginate, simplePaginate o cursorPaginate, y cómo procesar millones de filas con chunkById, lazy y cursor sin quedarte sin memoria.
minutes: 8
---

Con cien filas todo funciona. Con diez millones, `Order::all()` agota la memoria y una página lejana tarda segundos. Hay dos problemas distintos: **mostrar** datos por partes (paginar) y **procesarlos** por partes (recorrer).

## Paginar: tres métodos, tres compromisos

```php
Order::latest('id')->paginate(25);          // con total y números de página
Order::latest('id')->simplePaginate(25);    // solo «anterior» y «siguiente»
Order::latest('id')->cursorPaginate(25);    // solo «anterior» y «siguiente», por cursor
```

| Método | Cuenta el total | Páginas numeradas | Coste en páginas lejanas |
| --- | --- | --- | --- |
| `paginate` | Sí (una consulta extra) | Sí | Crece |
| `simplePaginate` | No | No | Crece |
| `cursorPaginate` | No | No | Estable, con índice |

La diferencia está en el SQL. Para «la segunda página» de 15 filas:

```sql
-- offset: recorre y descarta todo lo anterior
select * from users order by id asc limit 15 offset 15;

-- cursor: salta directo con un WHERE
select * from users where id > 15 order by id asc limit 15;
```

Según la documentación de Laravel, la paginación por cursor rinde mejor en tablas grandes **si las columnas del `ORDER BY` tienen índice**, porque el `offset` recorre todo lo anterior. Y en tablas que reciben escrituras constantes, la de offset puede **saltarse o repetir filas** si se añaden o borran registros mientras el usuario navega.

Sus límites: solo anterior y siguiente, el orden debe usar al menos una columna única (o una combinación única) y no admite valores `null` en esas columnas. Por eso `latest()` solo no basta: ordena por `created_at`, que puede repetirse. Añade el `id` como desempate:

```php
Order::orderByDesc('created_at')->orderByDesc('id')->cursorPaginate(25);
```

Para conservar los filtros de la URL en los enlaces:

```php
Order::query()
    ->where('status', $request->query('status', 'paid'))
    ->orderByDesc('id')
    ->cursorPaginate(25)
    ->withQueryString();
```

Si devuelves el paginador a Inertia o como JSON, se serializa solo: con `paginate` trae `data`, `current_page`, `last_page`, `total` y `links`; con `cursorPaginate`, `data`, `next_cursor`, `next_page_url`, `prev_cursor` y `prev_page_url`.

## Recorrer una tabla entera

Para un script o un job que toca todas las filas no uses `get()`. Tienes cuatro opciones:

```php
Order::chunk(200, fn ($orders) => /* ... */);
Order::chunkById(200, fn ($orders) => /* ... */);
Order::lazy();
Order::cursor();
```

- **`chunk`** trae bloques de 200 y llama a tu función con cada uno. Usa memoria para un bloque.
- **`chunkById`** hace lo mismo, pero avanza por clave primaria. Es la que debes usar si **modificas o borras filas** mientras recorres: con `chunk`, cambiar una columna que forma parte del filtro (o la clave primaria) puede hacer que se salten registros.
- **`lazy`** ejecuta la consulta por bloques por debajo, pero te entrega una `LazyCollection` plana, como si fuera un solo flujo. Su variante `lazyById` es el equivalente de `chunkById`.
- **`cursor`** lanza **una** consulta y va hidratando un modelo a la vez con un generador. No puede cargar relaciones con `with()`, y aun así puede agotar la memoria, porque el driver PDO guarda en su búfer todos los resultados sin procesar. Si tienes muchísimas filas, la documentación recomienda `lazy`.

Un ejemplo típico, archivar pedidos antiguos:

```php
Order::where('created_at', '<', now()->subYears(2))
    ->chunkById(500, function ($orders) {
        foreach ($orders as $order) {
            $order->update(['archived' => true]);
        }
    });
```

`chunkById` y `lazyById` añaden su propia condición `WHERE` a la consulta, así que lo normal es agrupar tus condiciones dentro de un closure: `->where(fn ($query) => $query->where('status', 'pending')->orWhere('status', 'failed'))`.

## Errores comunes

- Usar `chunk` y modificar en el callback la columna por la que filtras: se saltan filas. Usa `chunkById`.
- `paginate` sobre una tabla enorme cuando la interfaz solo necesita «siguiente»: pagas un `COUNT` que nadie mira.
- `cursorPaginate` ordenando por una columna que no es única.
- Confiar en `cursor()` para millones de filas con relaciones: no carga relaciones y puede agotar la memoria igual.

## Practica

Crea 100.000 pedidos con un factory y mide el tiempo y la memoria (`memory_get_peak_usage(true)`) de recorrerlos con `get()`, `chunkById(500)` y `lazy()`. Después pagina la página 4.000 con `paginate` y con `cursorPaginate` y compara el SQL que lanza cada uno con `DB::listen` o con `toSql()`.
