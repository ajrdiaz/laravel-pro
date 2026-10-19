---
title: Pagos que fallan y cómo probarlos sin dinero real
summary: Tarjetas de prueba, webhooks en local con el Stripe CLI, qué hacer con un pago que exige confirmación y cómo escribir pruebas de tu flujo de cobro.
minutes: 9
---

El camino feliz de un pago es corto. Casi todo el trabajo está en lo que pasa cuando algo sale mal: la tarjeta rechazada, el banco que pide confirmar, el cobro de renovación que falla un mes. Y todo eso se puede probar **sin gastar un euro**.

## Tarjetas de prueba

En el modo de pruebas de Stripe (lo llama *sandbox*) usas números de tarjeta que simulan cada situación. Con cualquier fecha futura, como `12/34`, y cualquier CVC de tres cifras:

| Número | Qué simula |
| --- | --- |
| `4242 4242 4242 4242` | Pago correcto |
| `4000 0025 0000 3155` | Con el cliente delante siempre pide autenticar; los cobros automáticos solo si no la guardaste antes para uso futuro |
| `4000 0027 6000 3184` | Exige autenticación en **todos** los pagos |
| `4000 0000 0000 9995` | Rechazada por fondos insuficientes |
| `4000 0000 0000 0341` | Se asocia al cliente sin problema, pero los cobros fallan |

Stripe pide no usar números de tarjeta en código del servidor, ni siquiera de prueba: en pruebas automáticas usa un método de pago como `pm_card_visa`.

## Webhooks en tu máquina con el Stripe CLI

Stripe no puede llamar a `localhost`. El Stripe CLI abre un puente: recibe los eventos y los reenvía a tu servidor local. La documentación de Cashier lo recomienda para desarrollar:

```bash
stripe listen --forward-to localhost:8000/stripe/webhook
stripe trigger payment_intent.succeeded
```

- `/stripe/webhook` es la ruta que Cashier registra por defecto.
- `stripe listen` imprime su propio **secreto de firma**. Ponlo en `STRIPE_WEBHOOK_SECRET` mientras pruebas en local: si no, Cashier rechazará los eventos por no coincidir la firma.
- `stripe trigger` fabrica un evento de ejemplo sin tener que hacer un pago.

## Cuando el pago exige algo más

Un pago puede fallar o necesitar que el cliente confirme (el «3D Secure»). Cashier lanza una excepción `IncompletePayment`, y tú decides:

```php
use Laravel\Cashier\Exceptions\IncompletePayment;

try {
    $subscription = $user->newSubscription('default', 'price_monthly')->create($paymentMethod);
} catch (IncompletePayment $exception) {
    return redirect()->route('cashier.payment', [
        $exception->payment->id,
        'redirect' => route('home'),
    ]);
}
```

La ruta `cashier.payment` es una página de confirmación que trae Cashier: pide de nuevo los datos de la tarjeta y hace los pasos extra de Stripe. Al terminar, redirige a la URL del parámetro `redirect`, añadiendo `message` y `success` a la dirección. La alternativa es dejar que Stripe lo gestione con sus correos de cobro automáticos, y en ese caso avisa al usuario de que recibirá un correo con instrucciones.

## Estados «incompletos»

Mientras la confirmación esté pendiente, la suscripción queda en `incomplete` o `past_due` (columna `stripe_status`), y **no estará activa** hasta que el cliente confirme el pago. Cashier la activa sola cuando Stripe avisa por webhook de que se completó. Para preguntarlo:

```php
if ($user->hasIncompletePayment('default')) {
    // enviarle a la página de confirmación
}
```

Las renovaciones también pueden pedir confirmación aunque la suscripción ya estuviera activa. Cashier puede avisar al cliente con una notificación: se activa con la variable `CASHIER_PAYMENT_NOTIFICATION`, que apunta a una clase de notificación (viene `Laravel\Cashier\Notifications\ConfirmPayment`, y está desactivada por defecto), y necesita el webhook `invoice.payment_action_required` habilitado.

## Orden y duplicados

Stripe no garantiza que los eventos lleguen en el orden en que ocurrieron: crear una suscripción genera `customer.subscription.created`, `invoice.created`, `invoice.paid`… en cualquier orden. Tampoco debes usar la marca de tiempo `created` para ordenar, porque es en segundos y varios eventos comparten la misma. Para detectar duplicados, Stripe recomienda **guardar los ids de evento**, como vimos en la lección anterior. Tu manejador no puede depender de que un evento llegue antes que otro: si te llega `invoice.paid` primero, consulta la API para obtener lo que falte.

## Probar tu flujo

La documentación de Cashier recomienda **no simular** las peticiones a Stripe: hacerlo obliga a reimplementar a medias su comportamiento. Es mejor dejar que las pruebas hablen con la API real de Stripe en modo de pruebas, aunque sea más lento. Pon la clave de pruebas en `phpunit.xml`:

```xml
<env name="STRIPE_SECRET" value="sk_test_..."/>
```

Dos ideas más de la documentación: prueba **tu** flujo de suscripción y pago, no cada comportamiento interno de Cashier (que ya tiene su propia batería de pruebas), y agrupa las pruebas lentas en su propio grupo para poder excluirlas.

Lo tuyo se prueba mejor sin red: tu código que reacciona a un webhook es un listener normal, y lo ejecutas disparando el evento con un payload de ejemplo:

```php
public function test_a_paid_invoice_sends_the_receipt()
{
    Notification::fake();
    $user = User::factory()->create(['stripe_id' => 'cus_123']);

    event(new WebhookReceived([
        'id' => 'evt_1',
        'type' => 'invoice.payment_succeeded',
        'data' => ['object' => ['customer' => $user->stripe_id]],
    ]));

    Notification::assertSentTo($user, ReceiptNotification::class);
}
```

## Errores comunes

- Probar en modo real «con una tarjeta propia» y luego pedir un reembolso.
- Usar el secreto de firma del panel de Stripe con `stripe listen`, que firma con el suyo.
- Suponer que `invoice.created` llega antes que `invoice.paid`.
- No decidir qué ve el usuario cuando un pago exige confirmación.
- Ordenar o deduplicar eventos por su marca de tiempo en lugar de por su id.

## Practica

Con una cuenta de Stripe en modo de pruebas, crea una suscripción con la tarjeta `4000 0000 0000 9995` y otra con `4000 0027 6000 3184`, y comprueba qué ve el usuario en cada caso. Después ejecuta `stripe listen` y `stripe trigger invoice.payment_succeeded`, y escribe un test de tu listener que dispare el mismo evento dos veces y compruebe que solo se envía un recibo.
