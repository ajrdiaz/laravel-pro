---
title: Funciones de flecha, callables y colecciones
summary: Cómo leer y escribir los callbacks que Laravel te pide a cada paso, y transformar datos con colecciones sin bucles ni sorpresas.
minutes: 8
---

Laravel está lleno de callbacks: `map`, `filter`, `when`, `transaction`, las rutas. Entenderlos bien es entender medio framework.

## Closures y funciones de flecha

Una closure clásica necesita `use` para ver variables de fuera. Una función de flecha las captura sola, **por valor**:

```php
$rate = 0.18;

$withTax = function (int $cents) use ($rate) {
    return (int) round($cents * (1 + $rate));
};

$withTax = fn (int $cents) => (int) round($cents * (1 + $rate));
```

Dos reglas: la flecha es **una sola expresión** (devuelve su resultado, sin `return`), y como captura por valor, cambiar una variable de fuera desde dentro no tiene efecto:

```php
$count = 1;
$bump = fn () => $count++;
$bump();

echo $count; // 1
```

Si necesitas varias líneas o modificar algo de fuera, usa una closure con `use (&$count)`.

## Callables de primera clase

Desde PHP 8.1 puedes convertir una función o un método en closure con `(...)`:

```php
$format = (new Fmt)->money(...);
$title = Str::title(...);

collect($names)->map(Str::title(...));
```

Es más corto que `fn ($n) => Str::title($n)` y se lee mejor. Solo tiene una trampa, que veremos abajo.

## Colecciones: transformar sin bucles

`collect()` envuelve un array con métodos encadenables:

```php
$orders = collect([
    ['customer' => 'Ana', 'status' => 'paid', 'cents' => 1000],
    ['customer' => 'Luis', 'status' => 'paid', 'cents' => 2500],
    ['customer' => 'Ana', 'status' => 'pending', 'cents' => 900],
    ['customer' => 'Ana', 'status' => 'paid', 'cents' => 700],
]);

$totals = $orders
    ->where('status', 'paid')
    ->groupBy('customer')
    ->map(fn ($group) => $group->sum('cents'))
    ->sortDesc();

// ['Luis' => 2500, 'Ana' => 1700]
```

Cada paso devuelve una colección nueva, así que el original no cambia. Los métodos que más usarás: `map`, `filter`, `reject`, `pluck`, `groupBy`, `keyBy`, `sum`, `sortBy`, `first`, `reduce`.

## Dos trampas con las claves

Las colecciones conservan las claves del array, y eso muerde:

```php
collect([1, 2, 3, 4])->filter(fn ($n) => $n % 2 === 0)->toJson();
// {"1":2,"3":4}   ← un objeto, no una lista

collect([1, 2, 3, 4])->filter(fn ($n) => $n % 2 === 0)->values()->toJson();
// [2,4]
```

Si vas a enviar el resultado a Vue o React, quita los huecos con `values()`.

La segunda: `map` llama a tu callback con el **valor y la clave**. Con una flecha no lo notas, pero con una función de PHP que tenga parámetros opcionales sí:

```php
collect(['10', '20'])->map(intval(...))->all();
// [10, 0]   ← intval recibió la clave como segundo argumento (la base)

collect(['10', '20'])->map(fn (string $v) => (int) $v)->all();
// [10, 20]
```

## Colecciones perezosas

Una colección normal tiene todos los elementos en memoria. `LazyCollection` los produce de uno en uno con un generador, y no hace nada hasta que consumes el resultado:

```php
LazyCollection::make(function () {
    $handle = fopen(storage_path('app/import.csv'), 'r');

    while (($line = fgets($handle)) !== false) {
        yield $line;
    }

    fclose($handle);
})
    ->chunk(1000)
    ->each(fn ($lines) => ImportChunk::dispatch($lines->all()));
```

Procesa un archivo enorme sin cargarlo entero en memoria: solo hay un bloque de mil líneas a la vez. En Eloquent, `cursor()` y `lazy()` devuelven una `LazyCollection` por la misma razón.

## Errores comunes

- Traer miles de modelos con `User::all()` para filtrarlos después con `->where(...)`: filtra en la consulta, no en PHP.
- Olvidar `values()` antes de serializar una colección filtrada.
- Pasar a `map` una función de PHP con parámetros opcionales (`intval`, `round`): usa una flecha.
- Escribir una cadena de diez métodos ilegible: parte la cadena en variables con nombre.

## Practica

Con el arreglo de pedidos de arriba, usa solo colecciones para sacar a los dos clientes que más han pagado, con su total en euros formateado. Primero con flechas y luego sustituye una de ellas por un callable de primera clase. Después comprueba qué devuelve `->toJson()` si te olvidas de `values()`.
