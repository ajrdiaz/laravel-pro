---
title: Presencia, whispers y qué hacer cuando se cae la conexión
summary: Saber quién está en un canal, evitar que el emisor reciba su propio mensaje, avisar entre navegadores sin pasar por el servidor y reaccionar a una conexión caída.
minutes: 9
---

Un chat o una pantalla compartida necesitan más que recibir eventos: hay que saber **quién está**, no duplicar lo que ya pintaste y no quedarse ciego si el WebSocket se cae.

## Canales de presencia

Un canal de presencia es un canal privado que además dice quién está dentro. Se declara en el evento con `PresenceChannel`:

```php
public function broadcastOn(): array
{
    return [new PresenceChannel('chat.'.$this->message->room_id)];
}
```

Su autorización es distinta: no devuelves `true`, devuelves un **arreglo con los datos del usuario**. Devolver `false` o `null` lo rechaza:

```php
Broadcast::channel('chat.{roomId}', function (User $user, int $roomId) {
    if ($user->canJoinRoom($roomId)) {
        return ['id' => $user->id, 'name' => $user->name];
    }
});
```

Ese arreglo lo verá **todo el que esté en la sala**. Pon solo lo necesario para pintar la lista: nunca el correo ni nada privado.

## Quién entra y quién sale

Con Echo, `join` te da tres callbacks:

```js
Echo.join(`chat.${roomId}`)
    .here((users) => { /* quienes ya estaban */ })
    .joining((user) => console.log(user.name))
    .leaving((user) => console.log(user.name))
    .error((error) => console.error(error));
```

`here` se ejecuta una vez al unirte y trae a los demás usuarios conectados. `joining` y `leaving` se disparan cuando alguien entra o sale. `error` salta si el endpoint de autorización responde algo distinto de 200. Con los hooks de `@laravel/echo-vue` (o `-react`), `useEchoPresence` escucha los eventos de un canal de presencia con la misma forma que `useEcho`.

## No te mandes tu propio mensaje

Cuando envías un mensaje, tu interfaz lo añade a la lista al recibir la respuesta HTTP. Si también escuchas el evento, lo verás dos veces. `toOthers()` excluye a quien lo causó:

```php
broadcast(new MessageSent($message))->toOthers();
```

Dos requisitos: el evento debe usar el trait `InteractsWithSockets`, y la petición debe llevar la cabecera `X-Socket-ID`. Echo la añade sola solo si usas una instancia global de Axios. **Esta app no instala Axios**, así que tendrías que enviarla tú, con el valor que da el hook `useSocketId` (o `Echo.socketId()`). Si olvidas la cabecera, `toOthers()` no excluye a nadie y el mensaje se duplica.

## Whispers: de navegador a navegador

Para avisos que no merecen pasar por Laravel, como «Ana está escribiendo…», Echo tiene los *client events*:

```js
Echo.private(`chat.${roomId}`).whisper('typing', { name: user.name });

Echo.private(`chat.${roomId}`).listenForWhisper('typing', (e) => {
    console.log(e.name);
});
```

El mensaje va directo a los demás clientes del canal **sin tocar tu aplicación**: no se valida, no se guarda y no puedes confiar en él. Úsalo solo para señales efímeras. Con Pusher hay que activar la opción «Client Events» en el panel de la aplicación; comprueba que tu servidor los admite probándolo.

## Nombre y contenido del evento

Dos métodos del evento te dan control:

```php
public function broadcastAs(): string
{
    return 'message.sent';
}

public function broadcastWith(): array
{
    return ['id' => $this->message->id, 'body' => $this->message->body];
}
```

`broadcastWith` define exactamente qué viaja al navegador, en lugar de todas las propiedades públicas. Y si cambias el nombre con `broadcastAs`, tienes que escucharlo con un punto al principio: `.listen('.message.sent', ...)`. Sin el punto, Echo le antepone el espacio de nombres de la app y no lo encuentra.

## Cuando la conexión se cae

Un evento es un aviso, no un buzón: si el navegador estuvo desconectado, no lo recibe después. La base de datos sigue siendo la fuente de verdad. El hook `useConnectionStatus` te dice en qué estado está el WebSocket:

- `connected`: conectado.
- `connecting`: primer intento en curso.
- `reconnecting`: intentando volver tras una desconexión.
- `disconnected`: sin conexión y sin intentarlo.
- `failed`: falló y no se reintentará.

Muestra un aviso discreto en `reconnecting` y `disconnected`, y cuando vuelvas a `connected` pide al servidor lo que te hayas perdido (por ejemplo, los mensajes posteriores al último que tienes).

## Errores comunes

- Devolver `true` en la autorización de un canal de presencia: hay que devolver los datos del usuario.
- Exponer datos privados en el arreglo de presencia.
- Usar `toOthers()` sin enviar `X-Socket-ID`: no excluye a nadie.
- Fiarte de un whisper como si lo hubiera validado el servidor.
- Dar por hecho que no te perdiste nada tras una reconexión.

## Practica

Sobre el proyecto «Chat de equipo en tiempo real», añade una lista de conectados con `here`, `joining` y `leaving`, un aviso «escribiendo…» con whispers y un banner que aparezca con `useConnectionStatus`. Corta el servidor de Reverb con la sala abierta, escribe un mensaje desde otra pestaña, vuelve a levantarlo y comprueba que la primera pestaña recupera lo que no recibió.
