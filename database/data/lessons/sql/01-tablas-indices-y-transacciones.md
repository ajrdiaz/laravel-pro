---
title: Tablas, índices y transacciones sin miedo
summary: Cómo diseñar un esquema que se mantiene sano, qué índices crear y cuándo envolver escrituras en una transacción.
minutes: 8
---

Un buen esquema ahorra más bugs que cualquier test, porque la base de datos puede impedir los datos imposibles aunque el código se equivoque.

## Una tabla, una cosa

```php
Schema::create('orders', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->constrained()->cascadeOnDelete();
    $table->string('status')->default('pending');
    $table->unsignedInteger('total_cents');
    $table->timestamps();

    $table->index(['user_id', 'status']);
});
```

- `foreignId(...)->constrained()` crea la **clave foránea**: la base de datos impide un pedido con un `user_id` que no existe.
- El dinero se guarda como **entero en centavos**. Un `float` acumula errores de redondeo.
- No repitas datos: el nombre del cliente vive en `users`, no copiado en cada pedido.

## JOIN: combinar tablas

```sql
SELECT users.name, SUM(orders.total_cents) AS spent
FROM users
JOIN orders ON orders.user_id = users.id
WHERE orders.status = 'paid'
GROUP BY users.id, users.name
ORDER BY spent DESC
LIMIT 5;
```

El mismo resultado con el query builder de Laravel:

```php
DB::table('users')
    ->join('orders', 'orders.user_id', '=', 'users.id')
    ->where('orders.status', 'paid')
    ->groupBy('users.id', 'users.name')
    ->selectRaw('users.name, SUM(orders.total_cents) as spent')
    ->orderByDesc('spent')
    ->limit(5)
    ->get();
```

`JOIN` (o `INNER JOIN`) solo devuelve las filas con pareja en las dos tablas. `LEFT JOIN` conserva todas las de la izquierda aunque no tengan pareja.

## Índices: el orden importa

El índice `(user_id, status)` se usa de izquierda a derecha:

| Filtro | ¿Lo aprovecha? |
| --- | --- |
| `user_id = 5` | Sí |
| `user_id = 5 AND status = 'paid'` | Sí, del todo |
| `status = 'paid'` | No: falta la primera columna |

Comprueba siempre con `EXPLAIN`:

```php
dd(DB::table('orders')->where('user_id', 5)->where('status', 'paid')->explain());
```

Si ves que recorre toda la tabla, falta un índice o el que tienes no sirve para esa consulta. Un índice acelera las lecturas pero encarece las escrituras, así que no indexes todo.

## Transacciones: todo o nada

```php
DB::transaction(function () use ($user, $cart) {
    $order = $user->orders()->create(['total_cents' => $cart->total()]);

    foreach ($cart->lines() as $line) {
        $order->lines()->create($line->toArray());
        $line->product->decrement('stock', $line->quantity);
    }
});
```

Si algo lanza una excepción dentro del callback, se revierte todo y no queda un pedido sin líneas ni stock descontado a medias.

Dentro de una transacción no hagas lo que no se puede deshacer: enviar un email o llamar a una API externa. Despacha esos jobs cuando la transacción ya se confirmó:

```php
SendReceipt::dispatch($order)->afterCommit();
```

## Migraciones

Una migración que ya corrió en producción no se edita: se crea otra que haga el cambio. Así todos los entornos llegan al mismo esquema siguiendo el mismo historial. `php artisan db:show` te da un resumen de la base de datos a la que estás conectado.

## Errores comunes

- Guardar dinero como `float`.
- Una clave foránea «lógica», sin restricción en la base de datos.
- Un índice con las columnas en el orden equivocado.
- Enviar un email dentro de una transacción que luego se revierte.

## Practica

Haz el reto de este módulo: diseña el esquema de una tienda con claves foráneas e índices, escribe las migraciones y una consulta que liste los 5 clientes que más gastaron, revisada con `EXPLAIN`.
