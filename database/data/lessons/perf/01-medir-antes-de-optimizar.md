---
title: Medir antes de optimizar
summary: Cómo encontrar lo que de verdad es lento, y entonces usar índices, caché y lotes en ese orden.
minutes: 7
---

La regla de oro: no optimices nada que no hayas medido. Casi siempre lo lento es una consulta, no el framework, y cachear a ciegas solo esconde el problema.

## 1. Mide

En local, Debugbar o Telescope te muestran cuántas consultas hace cada página y cuánto tarda cada una. En producción, Pulse. Y en código:

```php
DB::listen(fn ($query) => logger($query->sql, ['ms' => $query->time]));
```

Cuando encuentres una consulta lenta, pregúntale a la base de datos qué hace:

```php
dd(Order::where('status', 'paid')->explain());
```

`EXPLAIN` muestra si la consulta usa un índice o recorre toda la tabla.

## 2. Añade el índice correcto

```php
Schema::table('orders', function (Blueprint $table) {
    $table->index(['user_id', 'status']);
});
```

Indexa lo que usas en `where`, `join` y `orderBy`. El orden de las columnas importa: pon primero la que más filtra. Un índice acelera las lecturas pero encarece las escrituras, así que no indexes todo.

## 3. Cachea lo que sigue siendo caro

```php
$revenue = Cache::remember('dashboard.revenue', now()->addMinutes(10), function () {
    return Order::where('status', 'paid')->sum('amount');
});
```

`remember` guarda el resultado y lo reutiliza hasta que expira. Lo difícil no es guardar, sino **invalidar**: cuando cambian los datos, borra la entrada.

```php
// por ejemplo, desde un observer de Order
Cache::forget('dashboard.revenue');
```

## 4. Procesa en lotes

```php
Order::query()->chunkById(1000, fn ($orders) => /* ... */);
```

Para tablas grandes, `chunkById()` o `lazy()` mantienen baja la memoria. `all()` y `get()` cargan todo de una vez.

## 5. En producción

```bash
php artisan optimize
```

Cachea la configuración, las rutas, las vistas y los eventos. Después de `config:cache`, `env()` solo funciona dentro de los archivos de `config/`. En el resto del código usa `config()`.

## Errores comunes

- Cachear sin pensar cómo se invalida: los usuarios ven datos viejos.
- Indexar todas las columnas.
- «Optimizar» sin tener un antes y un después medidos.

## Practica

Haz el reto de este módulo: toma una página lenta, mídela con Debugbar, añade índices y caché con invalidación, y anota los tiempos antes y después.
