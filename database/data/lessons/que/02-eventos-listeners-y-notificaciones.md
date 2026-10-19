---
title: Eventos, listeners y notificaciones
summary: Cómo desacoplar los efectos secundarios de una acción, ponerlos en cola y probarlos.
minutes: 8
---

Cuando se paga un pedido hay que enviar un recibo, avisar a almacén, registrar una auditoría... Si todo eso vive dentro de la acción de pago, cada nuevo requisito la engorda y la rompe. Los **eventos** lo evitan.

## El evento: un hecho que ocurrió

```php
class OrderPaid
{
    use Dispatchable, SerializesModels;

    public function __construct(public Order $order) {}
}
```

Un evento es un objeto simple con los datos de lo que pasó. Su nombre va en pasado: `OrderPaid`, no `PayOrder`. Lo disparas una sola vez, desde donde ocurre el hecho:

```php
OrderPaid::dispatch($order);
```

La acción de pago no sabe ni le importa quién escucha.

## El listener: una reacción

```php
class SendReceipt implements ShouldQueue
{
    public function handle(OrderPaid $event): void
    {
        $event->order->user->notify(new ReceiptReady($event->order));
    }
}
```

Laravel descubre solo los listeners: mira el tipo del parámetro de `handle()` y lo asocia con ese evento. Añadir un nuevo efecto es crear otro listener; no tocas el código de pago.

`ShouldQueue` hace que el listener se ejecute en un **worker** y no durante la petición. El usuario no espera a que se envíe el email.

## Antes o después de confirmar

Si el evento se dispara dentro de una transacción, un listener en cola podría ejecutarse **antes** de que la transacción se confirme y no encontrar el pedido. Para evitarlo, implementa `ShouldDispatchAfterCommit` en el evento: Laravel esperará a que la transacción se confirme antes de despacharlo, y no lo despachará si se revierte.

## Notificaciones

Una notificación es un mensaje a una persona por uno o más canales (email, base de datos, Slack...):

```php
class ReceiptReady extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public Order $order) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Tu recibo')
            ->line('Gracias por tu compra.');
    }
}
```

Se envía con `$user->notify(new ReceiptReady($order))`. Si mañana también quieres avisar por Slack, añades el canal en `via()` y un método `toSlack()`; el resto no cambia.

## Probarlo

```php
Event::fake();

$action->handle($order);

Event::assertDispatched(OrderPaid::class);
```

Y en otro test, sin simular el evento, compruebas la reacción:

```php
Notification::fake();

(new SendReceipt)->handle(new OrderPaid($order));

Notification::assertSentTo($order->user, ReceiptReady::class);
```

Probar el evento y el listener por separado hace los tests pequeños y precisos.

## Cuándo un evento es demasiado

Un evento añade indirección: para entender qué pasa al pagar tienes que buscar los listeners. Úsalo cuando hay **varios efectos independientes**, cuando quien dispara no debería conocer a quien reacciona, o cuando un efecto es lento. Si solo hay una consecuencia y siempre ocurre, una llamada directa es más clara.

## Errores comunes

- Un listener que hace tanto que es una acción disfrazada.
- Olvidar el worker y preguntarse por qué nadie recibe el email.
- Esperar que los listeners corran en orden: cada uno es independiente.
- Disparar el evento dentro de una transacción sin pensar en `ShouldDispatchAfterCommit`.

## Practica

Saca de tu acción de «completar tarea» el envío de un aviso a un evento `TaskCompleted` con su listener en cola, y escribe un test para cada pieza.
