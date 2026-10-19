---
title: Qué pasa cuando llega una request
summary: Del archivo public/index.php a la respuesta, el recorrido que debes poder dibujar de memoria en una entrevista.
minutes: 6
---

Si puedes explicar este recorrido sin mirar nada, entiendes la mitad de Laravel. Casi todo lo demás (autenticación, validación, colas) es una pieza que se engancha en algún punto de él.

## El recorrido en cinco pasos

1. El servidor web (Nginx, Apache o `php artisan serve`) manda toda petición a `public/index.php`.
2. Ese archivo carga el autoload de Composer y arranca la aplicación desde `bootstrap/app.php`.
3. Laravel registra y arranca los **service providers**: así se llena el container.
4. La request atraviesa los **middleware**: primero los globales, luego los del grupo (`web` o `api`) y al final los de la ruta.
5. El router encuentra la ruta, resuelve el **route model binding** y llama al controlador. La respuesta hace el camino de vuelta por los mismos middleware, en orden inverso.

## Dónde se configura todo

Desde Laravel 11 ya no existe `app/Http/Kernel.php`. Las rutas, los middleware y las excepciones se declaran en `bootstrap/app.php`:

```php
return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureProfileIsComplete::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
```

`$middleware->web(append: [...])` agrega un middleware al final del grupo `web`. Abre el `bootstrap/app.php` de esta misma app y compáralo: registra `HandleInertiaRequests` de esa forma.

## Un middleware tiene dos mitades

```php
public function handle(Request $request, Closure $next): Response
{
    $start = microtime(true);       // antes: la request aún no llegó al controlador

    $response = $next($request);    // aquí ocurre todo lo de adentro

    $elapsed = round((microtime(true) - $start) * 1000);
    $response->headers->set('X-Response-Time', "{$elapsed}ms");   // después: ya hay respuesta

    return $response;
}
```

Lo que escribes antes de `$next($request)` corre a la ida y lo que escribes después, a la vuelta. Por eso un header de respuesta siempre va después.

## Route model binding

```php
Route::get('/posts/{post:slug}', fn (Post $post) => $post);
```

Si el parámetro coincide con una variable tipada como modelo, Laravel busca el registro (aquí por `slug`). Si no existe, responde **404** solo: lanza `ModelNotFoundException` y el manejador de excepciones la convierte.

## Errores comunes

- Poner lógica de negocio en un middleware. Un middleware decide si la request pasa o cómo se decora la respuesta. El trabajo real va en el controlador o en una clase de acción.
- Buscar `routes/api.php` y no encontrarlo. Desde Laravel 11 no viene instalado: `php artisan install:api` lo crea.
- Esperar sesión en rutas de API. El grupo `api` no la inicia.

## Practica

1. Ejecuta `php artisan route:list` y localiza qué middleware tiene cada ruta.
2. Haz el reto de este módulo: un middleware que devuelva el tiempo de cada request en `X-Response-Time`, sin mirar esta lección.
