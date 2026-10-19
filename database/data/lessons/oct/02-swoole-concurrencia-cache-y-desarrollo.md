---
title: Octane más allá de la velocidad: Swoole, caché en memoria y desarrollo
summary: Qué funciones solo existen con Swoole, cómo usar tareas concurrentes, la caché y las tablas en memoria, y cómo trabajar con Octane en local sin perseguir fugas a ciegas.
minutes: 8
---

Octane se vende por la velocidad, pero según el servidor que elijas tienes además herramientas que PHP-FPM no ofrece. Y trabajar a diario con una app que vive en memoria pide unos hábitos distintos.

## Elegir servidor es elegir funciones

Octane funciona sobre FrankenPHP, RoadRunner y Swoole (u Open Swoole). Para servir peticiones más rápido, los tres sirven. Pero algunas funciones **solo existen con Swoole**, según la documentación:

| Función | Qué hace |
| --- | --- |
| Tareas concurrentes | Ejecutar varias operaciones a la vez |
| Ticks | Ejecutar algo cada N segundos |
| Caché de Octane | Una caché compartida por todos los workers, en memoria |
| Tablas | Tablas en memoria compartidas |

Si no vas a usar ninguna, el servidor que más te convenga operar es el correcto. Si las quieres, tu servidor es Swoole. Decídelo antes de escribir código que dependa de ellas.

## Tareas concurrentes

Con Swoole, `Octane::concurrently` ejecuta varias funciones a la vez y devuelve los resultados en el mismo orden:

```php
use Laravel\Octane\Facades\Octane;

[$users, $servers] = Octane::concurrently([
    fn () => User::all(),
    fn () => Server::all(),
]);
```

Dos cosas que hay que saber. Cada tarea corre en un **proceso distinto** al de la petición (en los *task workers* de Swoole), así que no comparte con ella su memoria ni su estado. El número de procesos disponibles se fija al arrancar:

```bash
php artisan octane:start --workers=4 --task-workers=6
```

Y no pases más de **1024 tareas** a una sola llamada, por el límite del sistema de tareas de Swoole. Úsalo para consultas independientes que tardan; si dependen una de otra, no se pueden paralelizar.

## La caché de Octane

Con Swoole puedes usar el driver `octane`, que la documentación anuncia con hasta dos millones de operaciones por segundo. Se apoya en las tablas de Swoole, y lo que guardas lo ven **todos los workers**:

```php
Cache::store('octane')->put('framework', 'Laravel', 30);
```

Lo que guardes ahí se pierde cuando reinicias el servidor, así que sirve para datos que se pueden volver a calcular, no para nada que deba sobrevivir. El máximo de entradas se define en `config/octane.php`.

También tiene cachés por **intervalo**, que se refrescan solas y se registran en el `boot` de un provider:

```php
Cache::store('octane')->interval('random', function () {
    return Str::random(10);
}, seconds: 5);
```

## Tablas

Las tablas son almacenamiento compartido entre workers, más simple todavía. Se declaran en `config/octane.php`, con el límite de filas tras los dos puntos:

```php
'tables' => [
    'example:1000' => [
        'name' => 'string:1000',
        'votes' => 'int',
    ],
],
```

```php
Octane::table('example')->set('uuid', ['name' => 'Nuno Maduro', 'votes' => 1000]);

Octane::table('example')->get('uuid');
```

Los tipos de columna son `string`, `int` y `float`, y los datos se pierden al reiniciar.

## Ticks

Un *tick* ejecuta una función cada cierto número de segundos dentro del servidor:

```php
Octane::tick('simple-ticker', fn () => logger('Tick'))
    ->seconds(10);
```

Es útil para tareas ligeras y repetidas (refrescar un dato en la caché). No sustituye al scheduler de Laravel ni a las colas: si el servidor se reinicia, el tick empieza de nuevo.

## Trabajar con Octane en local

Cada worker tiene el código viejo en memoria, así que tras un cambio hay que reiniciar. En desarrollo, deja que lo haga Octane:

```bash
npm install --save-dev chokidar
php artisan octane:start --watch
```

`--watch` necesita Node y la librería Chokidar, y las carpetas vigiladas se configuran con la opción `watch` de `config/octane.php`. Para ver si el servidor está vivo, `php artisan octane:status`.

La documentación recomienda **vigilar el uso de memoria en local** para no introducir fugas. Un método sencillo: crea una ruta temporal que devuelva `memory_get_usage(true)`, llámala mil veces seguidas y mira la curva. Si crece sin parar, algo guarda datos entre peticiones.

## Errores comunes

- Escribir código con `Octane::concurrently` o la caché de Octane y desplegar con un servidor que no es Swoole.
- Pasar a una tarea concurrente datos que dependen de la petición: corre en otro proceso.
- Guardar en la caché de Octane algo que no se puede recalcular tras un reinicio.
- Dejar un `--watch` activo en producción.
- Dar por bueno el rendimiento sin vigilar la memoria durante un rato.

## Practica

Arranca Octane con Swoole y escribe una ruta que lance dos consultas con `Octane::concurrently`. Mide con una pausa artificial de un segundo en cada consulta cuánto tarda la ruta con `concurrently` y sin él. Después añade la ruta temporal de memoria, llámala mil veces y comprueba que la curva es plana; introduce a propósito una propiedad estática que crece y observa la diferencia.
