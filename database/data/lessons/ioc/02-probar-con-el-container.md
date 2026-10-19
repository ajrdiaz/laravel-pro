---
title: Probar con el container, fakes, mocks e instancias
summary: Cómo sustituir dependencias en un test, cuándo usar un fake y cuándo un mock, y el binding contextual.
minutes: 7
---

El container no solo arma tus objetos: es lo que permite **cambiar una pieza en un test** sin tocar el código que la usa.

## instance: poner tu propio objeto

```php
$this->instance(ExchangeRates::class, new FixedRates(rate: 3.75));
```

A partir de esa línea, todo lo que pida `ExchangeRates` al container recibe tu objeto. El controlador, la acción y el job no saben que están en un test.

## fake frente a mock

Un **fake** es una implementación real pero simple, escrita por ti. Un **mock** es un objeto que **verifica** cómo lo usaron.

```php
// fake: sabe responder, no verifica nada
final class FixedRates implements ExchangeRates
{
    public function rate(string $from, string $to): float
    {
        return 3.75;
    }
}

// mock: espera una llamada concreta
$this->mock(ExchangeRates::class, function (MockInterface $mock) {
    $mock->expects('rate')->with('USD', 'PEN')->andReturn(3.75);
});
```

`$this->mock()` crea el objeto con Mockery **y lo registra en el container** en un solo paso. Si el código no llama a `rate('USD', 'PEN')`, el test falla.

Regla práctica: usa un **fake** cuando solo te importa el resultado. Usa un **mock** cuando lo importante es *que* se haya hecho la llamada, por ejemplo «se cobró una sola vez». Abusar de los mocks ata el test a la implementación: cualquier refactor lo rompe.

`partialMock` te deja mockear solo algunos métodos de un objeto real.

## Los fakes que ya trae Laravel

Para lo que Laravel ya ofrece, no escribas nada:

```php
Queue::fake();
Mail::fake();
Notification::fake();
Event::fake();
Storage::fake('public');
Http::fake();
```

Cada uno sustituye el servicio real por uno que registra lo ocurrido, y luego compruebas con `assertPushed`, `assertSent`, `assertDispatched`...

## Binding contextual

A veces dos clases piden la misma interfaz y necesitan implementaciones distintas:

```php
$this->app->when(PhotoController::class)
    ->needs(Filesystem::class)
    ->give(fn () => Storage::disk('photos'));

$this->app->when(VideoController::class)
    ->needs(Filesystem::class)
    ->give(fn () => Storage::disk('videos'));
```

El container entrega un disco u otro según **quién** lo pide. Es la alternativa limpia a un `if` dentro de la clase.

## El test que enseña un mal diseño

Si para probar una clase tienes que mockear cinco dependencias, la clase hace demasiado. Si no puedes sustituir una dependencia porque la clase hace `new StripeClient()` por dentro, falta inyectarla. Un test difícil de escribir suele estar avisando de un problema de diseño.

## Errores comunes

- Mockear todo y acabar probando que el mock hace lo que le dijiste.
- Usar `app()->make(...)` dentro de una clase en lugar de pedir la dependencia por constructor: no hay forma limpia de sustituirla.
- Olvidar que `$this->instance()` solo afecta a lo que se resuelve **después** de llamarlo.

## Practica

Toma el reto del módulo y escribe dos tests de la misma clase: uno con un fake de `ExchangeRateProvider` y otro con un mock que compruebe que se llama exactamente una vez.
