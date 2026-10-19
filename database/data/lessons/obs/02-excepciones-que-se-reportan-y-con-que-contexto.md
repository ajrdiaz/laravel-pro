---
title: Excepciones: qué se reporta, con qué contexto y cuánto
summary: Cómo decide Laravel qué excepciones registrar, cómo añadirles contexto útil, cómo bajar el ruido sin perder lo importante y cómo separar lo que ve el usuario de lo que ves tú.
minutes: 8
---

Los logs que escribes tú dicen lo que **esperabas** que pasara. Las excepciones dicen lo que **no**. Son el dato más valioso de una aplicación en producción, y también el más fácil de convertir en ruido.

## Reportar y mostrar son dos cosas

Cuando algo lanza una excepción, Laravel hace dos trabajos distintos:

- **Reportar**: registrarla en el log o mandarla a un servicio externo, para ti.
- **Renderizar**: decidir qué ve el usuario (una página 500, un JSON, una redirección).

Los dos se configuran en `bootstrap/app.php`, dentro de `withExceptions`. Esta app ya usa ese bloque para decidir cuándo responder en JSON:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

A veces quieres reportar un error pero seguir con la petición, por ejemplo cuando lo capturas y devuelves un valor de reserva. Para eso existe el helper `report()`:

```php
try {
    $this->validateAgainstProvider($value);
} catch (Throwable $e) {
    report($e);   // queda registrada, pero el usuario no ve una página de error

    return false;
}
```

Capturar una excepción y no hacer nada, sin `report()`, es la forma más común de perder un bug para siempre.

## Lo que Laravel ignora por defecto

No todo merece una alerta. Laravel no reporta, de serie, errores HTTP (como un 404), fallos de validación, de autenticación y de autorización, un modelo no encontrado o un token CSRF inválido. Lo comprobé reportando un 404 y una `ValidationException` en esta app: ninguno llegó al log.

Para ignorar tus propias excepciones, tienes dos formas:

```php
$exceptions->dontReport([InvalidOrderException::class]);
```

o marcar la clase con la interfaz `ShouldntReport`. Y si quieres reportar algo que Laravel ignora, `stopIgnoring()`. Las ignoradas aún pueden tener su propia respuesta al usuario.

## Contexto: lo que necesitas a las tres de la mañana

Laravel añade solo el **id del usuario autenticado** a cada excepción que registra. Al reportar una excepción de pedido en esta app, la línea del log incluye `"userId":7`. Para añadir más, hay dos niveles.

Contexto global, para todas las excepciones:

```php
$exceptions->context(fn () => ['request_id' => request()->header('X-Request-Id')]);
```

Y contexto propio de una excepción, con un método `context()` en la clase:

```php
class InvalidOrderException extends Exception
{
    public function __construct(public int $orderId)
    {
        parent::__construct('Pedido inválido');
    }

    /** @return array<string, mixed> */
    public function context(): array
    {
        return ['order_id' => $this->orderId];
    }
}
```

Con eso, el log queda así: `Pedido inválido {"order_id":42,"userId":7,"exception":...}`. Ya sabes qué pedido y qué usuario sin reproducir nada.

## Nivel de gravedad

Por defecto se registran como `error`. Cambia el nivel de las que no lo merecen o las que son urgentes:

```php
$exceptions->level(PDOException::class, LogLevel::CRITICAL);
```

## Bajar el ruido sin perder lo importante

Si una dependencia se cae, puedes recibir diez mil excepciones iguales en un minuto, y la alerta que importa se pierde. Laravel ofrece tres controles:

```php
// Muestrear: registrar una de cada mil
$exceptions->throttle(fn (Throwable $e) => Lottery::odds(1, 1000));

// Limitar: como mucho 300 por minuto de un tipo
$exceptions->throttle(function (Throwable $e) {
    if ($e instanceof BroadcastException) {
        return Limit::perMinute(300);
    }
});

// No repetir la misma instancia si se reporta varias veces
$exceptions->dontReportDuplicates();
```

`throttle` puede devolver `null` para dejar pasar todo lo demás. Por defecto, el límite se aplica por clase de excepción; con `->by($e->getMessage())` lo haces por mensaje.

## Enviarlas a otro sitio

Para mandar un tipo de excepción a un servicio externo, registra un callback de `report`. Si devuelve `false` o encadenas `->stop()`, Laravel no la registra además por el canal normal:

```php
$exceptions->report(function (InvalidOrderException $e) {
    // enviar a tu servicio de errores
})->stop();
```

## Errores comunes

- Capturar con `catch (Throwable)` y seguir sin llamar a `report()`: el error desaparece.
- Una excepción sin contexto: «Pedido inválido», ¿cuál?
- Alertar por cada 404 o error de validación: ya vienen ignorados, no los reactives sin motivo.
- Mandar a las alertas miles de copias de lo mismo en vez de muestrear o limitar.
- Incluir datos sensibles en `context()`: acaban en el log y en servicios de terceros.

## Practica

Crea una excepción `PaymentRejectedException` con un método `context()` que devuelva el id del pedido. Lánzala desde una ruta y comprueba en `storage/logs/laravel.log` que aparecen tu contexto y el `userId`. Después añade un `throttle` con `Limit::perMinute(5)` para esa clase, lánzala diez veces seguidas y cuenta cuántas líneas se registraron.
