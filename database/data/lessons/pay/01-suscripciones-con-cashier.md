---
title: Suscripciones con Cashier sin sustos
summary: Cobrar con Stripe Checkout, confiar en los webhooks y manejar periodos de gracia y duplicados.
minutes: 9
---

Cobrar es la parte donde un error cuesta dinero de verdad. Cashier envuelve la API de Stripe, pero las reglas de oro no las pone el paquete: las pones tú.

## Preparar el modelo

```php
use Laravel\Cashier\Billable;

class User extends Authenticatable
{
    use Billable;
}
```

`Billable` le da al modelo los métodos para crear clientes, suscripciones y cobros. Las credenciales van en variables de entorno (`STRIPE_KEY`, `STRIPE_SECRET` y `STRIPE_WEBHOOK_SECRET`). **Empieza siempre en modo de pruebas**: Stripe tiene claves y tarjetas de prueba para eso.

## Cobrar con Stripe Checkout

```php
Route::get('/subscribe', function (Request $request) {
    return $request->user()
        ->newSubscription('default', 'price_basic_monthly')
        ->trialDays(5)
        ->checkout([
            'success_url' => route('billing.success'),
            'cancel_url' => route('billing.cancel'),
        ]);
});
```

El usuario va a una página de Stripe a escribir su tarjeta. **Tus servidores nunca ven el número**, y eso te ahorra una enorme carga de seguridad y de cumplimiento normativo. `price_basic_monthly` es el id del precio que creaste en el panel de Stripe.

## Confía en el webhook, no en la redirección

Tras pagar, Stripe devuelve al usuario a tu `success_url`. **Eso no prueba nada**: cualquiera puede escribir esa URL a mano, y un usuario que cierra la pestaña antes de volver nunca la visita. El estado real del pago lo dicen los **webhooks**, mensajes que Stripe envía directamente a tu servidor, firmados.

Cashier ya registra la ruta `/stripe/webhook` y verifica la firma con `STRIPE_WEBHOOK_SECRET`. Para crear el webhook en Stripe:

```bash
php artisan cashier:webhook
```

Para la suscripción con Checkout, Stripe debe enviar el evento `customer.subscription.created`: es el que crea el registro de la suscripción en tu base de datos.

## Reaccionar a un evento de Stripe

```php
use Laravel\Cashier\Events\WebhookReceived;

Event::listen(function (WebhookReceived $event) {
    if ($event->payload['type'] === 'invoice.payment_succeeded') {
        // enviar el recibo, activar algo...
    }
});
```

## Duplicados: tu código debe ser idempotente

Stripe reintenta un webhook cuando tu servidor no responde con un 2xx, así que **el mismo evento puede llegar dos veces** y no siempre en orden. Guarda el id del evento y comprueba si ya lo procesaste:

```php
$id = $event->payload['id'];

if (DB::table('processed_stripe_events')->where('stripe_id', $id)->exists()) {
    return; // ya aplicado
}

// ... hacer el trabajo ...

DB::table('processed_stripe_events')->insert(['stripe_id' => $id]);
```

Con una restricción `unique` sobre `stripe_id` la protección aguanta incluso dos webhooks simultáneos.

## Preguntar por el estado

```php
$user->subscribed('default');                         // ¿tiene una suscripción válida?
$user->subscription('default')->onGracePeriod();      // ¿canceló pero aún le queda ciclo pagado?
$user->subscription('default')->cancelNow();          // terminarla ya
```

Al cancelar, la suscripción entra en un **periodo de gracia**: sigue válida hasta el final del ciclo que el cliente ya pagó. Por eso `subscribed()` devuelve `true` durante ese tiempo.

Un middleware que exija un plan activo:

```php
public function handle(Request $request, Closure $next): Response
{
    if (! $request->user()?->subscribed('default')) {
        return redirect('/billing');
    }

    return $next($request);
}
```

## Errores comunes

- Activar el plan al ver `?success=true` en la URL.
- Procesar dos veces el mismo webhook y enviar dos recibos, o dar dos meses gratis.
- Probar directamente con la tarjeta real.
- Olvidar qué pasa con un pago fallido: decide qué ve el usuario antes de que ocurra.

## Practica

Haz el reto de este módulo: un plan mensual en modo de pruebas con una página de precios, Stripe Checkout, un middleware que exija suscripción activa y un test del manejo de un webhook duplicado.
