---
title: De excepción a respuesta, cómo Laravel produce un 404, un 422 o un 500
summary: Qué excepciones se convierten en qué códigos HTTP y cómo personalizar las respuestas de error en bootstrap/app.php.
minutes: 7
---

Cuando algo sale mal, Laravel no te muestra un error en bruto: lo pasa por el **manejador de excepciones**, que decide qué respuesta HTTP devolver. Entender ese mapa te ahorra mucho código innecesario.

## El mapa por defecto

| Qué ocurre | Respuesta |
| --- | --- |
| `abort(404)` o una ruta que no existe | 404 |
| `ModelNotFoundException` (route model binding, `findOrFail`) | 404 |
| `ValidationException` | 422 con los errores en JSON, o una redirección atrás con los errores en una web |
| `AuthenticationException` | 401 en una API, o una redirección al login en una web |
| `AuthorizationException` (una policy dice que no) | 403 |
| Cualquier otra excepción | 500 |

Por eso no escribes `if (! $post) { return response('No existe', 404); }`: `Post::findOrFail($id)` o el route model binding ya lo hacen. Y por eso un form request no necesita devolver nada si falla: lanza la `ValidationException` y el resto ocurre solo.

## abort: parar con un código

```php
abort(403, 'No puedes editar este pedido.');
abort_if($order->isShipped(), 409, 'El pedido ya fue enviado.');
abort_unless($user->isAdmin(), 403);
```

Son atajos para lanzar una excepción HTTP desde cualquier punto del código.

## JSON o HTML

¿La respuesta de error debe ser una página o JSON? Depende de quién pregunta. Laravel responde JSON cuando la petición lo pide con `Accept: application/json`, y esta app lo ha ampliado a las rutas de la API en `bootstrap/app.php`:

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->shouldRenderJsonWhen(
        fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
    );
})
```

## Personalizar una respuesta

```php
->withExceptions(function (Exceptions $exceptions): void {
    $exceptions->render(function (NotFoundHttpException $e, Request $request) {
        if ($request->is('api/*')) {
            return response()->json(['message' => 'No encontrado.'], 404);
        }
    });
})
```

El callback recibe la excepción y la request. Si devuelve una respuesta, esa se usa; si devuelve `null`, Laravel sigue con su comportamiento normal. Así puedes personalizar solo lo que te interesa.

## Qué se registra y qué no

Cada excepción se **reporta** (va al log o a Sentry) y se **renderiza** (se convierte en respuesta). Son dos pasos independientes:

```php
$exceptions->report(function (InvalidOrderException $e) {
    // enviar a un servicio externo, por ejemplo
});

$exceptions->dontReport(InvalidOrderException::class);
```

Un 404 o un 422 son errores normales del uso de tu app y no deberían despertar a nadie; por eso Laravel ya los excluye del reporte. Un 500 sí es tuyo.

## En producción

Con `APP_DEBUG=false`, un 500 muestra una página genérica y **no revela nada** de tu código. Con `APP_DEBUG=true` muestra la traza, las rutas y a veces variables de entorno: nunca en producción.

Para crear tus propias páginas de error, basta una vista `resources/views/errors/404.blade.php` (o `500`, `403`...).

## Errores comunes

- Capturar una excepción con `try/catch` solo para devolver un 404 que `findOrFail` habría dado.
- Un `catch (\Exception $e) {}` vacío: el error desaparece y nadie lo ve.
- Devolver 200 con `{ "error": ... }` en una API.

## Practica

Prueba con `php artisan route:list` qué rutas hay y visita una que no exista, otra con un id inexistente y otra con datos inválidos. Anota qué código recibes en cada caso y con qué excepción se corresponde.
