---
title: Caché bien hecha, claves, tiempos e invalidación
summary: Qué cachear, cómo nombrar las claves, cuándo expira y cómo borrar lo viejo sin romper nada.
minutes: 8
---

Hay un chiste viejo: solo hay dos cosas difíciles en informática, invalidar la caché y nombrar cosas. Esta lección va de las dos.

## Cachear solo lo que lo merece

Antes de cachear, comprueba que es **lento** y que se **lee mucho más de lo que cambia**. Una consulta de 5 ms no necesita caché. Un cálculo de 800 ms que se pide en cada visita al dashboard, sí.

```php
$revenue = Cache::remember('dashboard.revenue', now()->addMinutes(10), function () {
    return Order::where('status', 'paid')->sum('total_cents');
});
```

`remember` devuelve el valor guardado si existe y, si no, ejecuta el callback, lo guarda y lo devuelve.

## Claves: que no se pisen

La clave identifica el dato. Si el dato **depende de algo**, eso va en la clave:

```php
// MAL: todos los usuarios comparten el mismo dato
Cache::remember('dashboard.stats', 600, fn () => $this->statsFor($user));

// BIEN: uno por usuario
Cache::remember("dashboard.stats.user.{$user->id}", 600, fn () => $this->statsFor($user));
```

El primer ejemplo es un **fallo de seguridad**: el primer usuario que entra deja sus datos en la clave y los siguientes ven los suyos. Cualquier cosa que cambie según el usuario, el idioma o el filtro debe formar parte de la clave.

## Tiempos de vida

Un TTL (tiempo de vida) corto es la forma más simple de invalidar: aceptas que el dato esté desfasado hasta unos minutos y te olvidas del problema. Para un panel de estadísticas suele ser perfecto. Para el stock de un producto en el momento de comprar, no.

`rememberForever` guarda sin caducidad. Úsalo solo si **tú** borras la entrada cuando el dato cambia.

## Invalidar: borrar cuando cambia

```php
// en un observer del modelo Order
public function saved(Order $order): void
{
    Cache::forget('dashboard.revenue');
}
```

Es explícito y fácil de seguir. El riesgo es olvidarse de un sitio donde el dato cambia (un comando, un import, un job). Si dudas, combina las dos cosas: invalida **y** pon un TTL como red de seguridad.

## Tags: invalidar un grupo

```php
Cache::tags(['products'])->remember("product.{$id}", 600, fn () => Product::find($id));

Cache::tags(['products'])->flush();   // borra todos los productos cacheados
```

Las tags no funcionan con los drivers `file`, `dynamodb`, `database` ni `storage`; necesitas Redis o Memcached. Si tu driver no las soporta, usa claves con un prefijo y bórralas por separado.

## La estampida

Cuando una entrada cara caduca y cien peticiones la piden a la vez, las cien la recalculan. Un **lock** hace que solo una trabaje:

```php
$lock = Cache::lock('build-report', 30);

if ($lock->get()) {
    try {
        // el cálculo caro, ejecutado por una sola petición
    } finally {
        $lock->release();
    }
}
```

Los locks atómicos necesitan un driver que los soporte, como Redis, Memcached o database.

## Qué driver usar

- `array`: solo en tests.
- `file` o `database`: valen para empezar y para sitios pequeños.
- `redis`: lo habitual en producción, sobre todo con colas, locks y tags.

## Errores comunes

- Una clave sin el id del usuario cuando el dato es personal.
- `rememberForever` sin nada que lo invalide.
- Cachear el resultado de una consulta lenta en lugar de arreglarla con un índice.
- Probar con la caché activada y no entender por qué «no se actualiza».

## Practica

Toma la página lenta del reto del módulo, cachea su parte más cara con una clave que incluya al usuario, invalida desde un observer y comprueba con un test que el dato se actualiza tras un cambio.
