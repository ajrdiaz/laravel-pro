---
title: El container, o cómo Laravel arma tus objetos
summary: Autowiring, bind, singleton y scoped; por qué se enlaza una interfaz a una implementación y cómo se prueba.
minutes: 7
---

Cuando escribes `public function __construct(InvoiceService $invoices)`, nadie hace `new`. El **service container** ve el tipo, construye el objeto y se lo entrega a quien lo pidió. A eso se le llama inyección de dependencias con _autowiring_.

## Autowiring sin configurar nada

```php
class InvoiceController
{
    public function __construct(private InvoiceService $invoices) {}
}
```

Si `InvoiceService` es una clase concreta y sus propias dependencias también se pueden construir, no necesitas registrar nada.

## Enlazar una interfaz a una implementación

Cuando pides una interfaz, el container no sabe qué clase usar. Se lo dices en un provider:

```php
interface ExchangeRates
{
    public function rate(string $from, string $to): float;
}

final class FixedRates implements ExchangeRates
{
    public function rate(string $from, string $to): float
    {
        return 3.75;
    }
}

// app/Providers/AppServiceProvider.php
public function register(): void
{
    $this->app->bind(ExchangeRates::class, FixedRates::class);
}
```

Ahora cambiar de proveedor de tasas es editar una línea, y el controlador ni se entera.

## bind, singleton y scoped

| Método      | Cuántas instancias                                          |
| ----------- | ----------------------------------------------------------- |
| `bind`      | Una nueva cada vez que se resuelve                          |
| `singleton` | Una sola durante toda la vida de la aplicación              |
| `scoped`    | Una sola por request; con Octane se reinicia entre requests |

## register frente a boot

- `register()` es solo para bindings. Otros providers quizá todavía no se hayan registrado.
- `boot()` corre cuando todo ya está registrado. Es el lugar de los observers, las macros, los gates y los view composers.

## Las facades por dentro

Una facade como `Cache::get(...)` es un acceso estático que, por debajo, pide el servicio al container y le reenvía la llamada. Por eso se pueden falsear en los tests: `Mail::fake()`, `Queue::fake()`.

## Probar con otra implementación

```php
$this->app->instance(ExchangeRates::class, new class implements ExchangeRates {
    public function rate(string $from, string $to): float
    {
        return 1.0;
    }
});
```

`instance` reemplaza lo que el container entregaría. El código bajo prueba recibe tu fake sin saberlo.

## Errores comunes

- Meter el `Request` o el propio container en un singleton. Con Octane quedan obsoletos entre requests.
- Resolver con `app(...)` por todos lados en lugar de inyectar por constructor. Funciona, pero esconde las dependencias.
- Usar servicios de otros providers dentro de `register()`.

## Practica

Haz el reto de este módulo: una interfaz `ExchangeRateProvider` con dos implementaciones, enlazada en un provider y reemplazada en un test con `$this->app->instance()`.
