---
title: Fakes, factories y llamadas HTTP en los tests
summary: Probar código que habla con servicios externos, construir datos con factories y fijar el tiempo.
minutes: 8
---

Un test útil es **rápido, determinista y no toca el mundo exterior**. Estas herramientas te lo dan: factories para los datos, fakes para los servicios y control del tiempo.

## Factories: datos con intención

```php
// una tarea cualquiera
Task::factory()->create();

// con relaciones
$user = User::factory()->has(Task::factory()->count(3))->create();
Task::factory()->for($user)->create();

// con valores concretos o variaciones
Task::factory()->count(2)->sequence(
    ['status' => 'open'],
    ['status' => 'done'],
)->create();
```

- `has()` crea los hijos junto al padre, `for()` asocia a uno que ya tienes.
- `sequence()` reparte valores distintos entre varios modelos. Los tests de esta app la usan para crear lecciones con posiciones y slugs concretos.
- Un **estado** con nombre deja el test legible: `Order::factory()->paid()->create()`.

Crea **solo lo que el test necesita**. Un test que arma cincuenta registros para comprobar uno es lento y esconde lo importante.

## Http::fake: nada sale a internet

```php
Http::preventStrayRequests();

Http::fake([
    'api.rates.test/*' => Http::response(['PEN' => 3.75]),
]);

$total = app(ConvertPrice::class)->handle(1000, 'PEN');

Http::assertSent(fn ($request) => str_contains($request->url(), 'api.rates.test'));
```

- `Http::fake([...])` responde con lo que digas en lugar de llamar a la API real.
- `preventStrayRequests()` hace que **cualquier petición sin un fake lance una excepción**. Es tu red de seguridad: si el código llama a un servicio que olvidaste simular, el test falla en vez de salir a internet en silencio.
- `Http::assertSent()` comprueba qué se envió.

Prueba también el camino triste: una respuesta 500 o un timeout.

```php
Http::fake(['api.rates.test/*' => Http::response([], 500)]);
```

## Los fakes de Laravel

| Fake | Comprueba con |
| --- | --- |
| `Queue::fake()` | `assertPushed`, `assertNothingPushed` |
| `Mail::fake()` | `assertSent`, `assertQueued` |
| `Notification::fake()` | `assertSentTo` |
| `Event::fake()` | `assertDispatched` |
| `Storage::fake('public')` | `Storage::disk('public')->assertExists(...)` |

Cuidado con `Event::fake()` sin argumentos: **ningún listener corre**. Si pruebas una acción que dispara un evento cuyo listener necesitas ver ejecutarse, no lo falsees, o limítalo a un evento concreto.

## Controlar el tiempo

```php
$this->travelTo(now()->setDate(2026, 3, 1)->setTime(9, 0));

// ... el código bajo prueba ve esa fecha ...
```

Para probar «la suscripción vence en 30 días» o «el cupón caducó ayer» necesitas decidir qué hora es. Nada de `sleep()`.

## Qué probar dónde

- **Feature test**: una ruta de principio a fin, con base de datos y fakes de lo externo.
- **Unit test**: una clase con lógica pura, sin framework.
- Prueba **la lógica de un job** (`handle()`) por separado de **que se despache**.

## Errores comunes

- Dejar que un test llame a una API real: lento, frágil y a veces con costo.
- Factories que crean medio mundo por defecto.
- `Event::fake()` global que oculta un bug del listener.
- Tests que dependen de la fecha actual.

## Practica

Escribe un test de tu servicio de tasas de cambio con `Http::fake()` para tres casos: respuesta correcta, error 500 y timeout, con `preventStrayRequests()` activado.
