---
title: Eventos en vivo con Reverb y Echo
summary: Instalar Reverb, emitir un evento por un canal privado y escucharlo en Vue o React con useEcho.
minutes: 9
---

Con WebSockets, el servidor puede avisar al navegador cuando algo pasa, sin que este tenga que preguntar cada pocos segundos. El caso clásico: mostrar el progreso de un job mientras el usuario mira la pantalla.

## Instalar

```bash
php artisan install:broadcasting
```

El comando prepara `config/broadcasting.php`, `routes/channels.php` y Reverb con una configuración razonable, y te ofrece instalar las dependencias de JavaScript. Después necesitas tres procesos corriendo:

```bash
php artisan reverb:start      # el servidor WebSocket
php artisan queue:work        # los eventos de broadcast se envían por la cola
npm run dev
```

## Un evento que se emite

```php
class ImportProgressed implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(
        public int $userId,
        public int $percent,
    ) {}

    /** @return array<int, Channel> */
    public function broadcastOn(): array
    {
        return [new PrivateChannel("imports.{$this->userId}")];
    }
}
```

Las propiedades públicas forman el contenido del mensaje. `ShouldBroadcast` lo envía por la cola; con `ShouldBroadcastNow` se envía de inmediato. Desde el job lo disparas con `ImportProgressed::dispatch($userId, $percent)`.

## Autorizar el canal

Un canal privado necesita que el servidor decida quién puede escucharlo, en `routes/channels.php`:

```php
Broadcast::channel('imports.{userId}', function (User $user, int $userId) {
    return $user->id === $userId;
});
```

Sin esto, o con una regla floja, otro usuario podría suscribirse al canal ajeno y leer sus datos.

## Escuchar en el navegador

Con los starter kits, el hook `useEcho` se encarga. En Vue:

```vue
<script setup lang="ts">
import { useEcho } from '@laravel/echo-vue';
import { ref } from 'vue';

const props = defineProps<{ userId: number }>();
const percent = ref(0);

useEcho(`imports.${props.userId}`, 'ImportProgressed', (event: { percent: number }) => {
    percent.value = event.percent;
});
</script>

<template>
    <progress :value="percent" max="100" />
</template>
```

En React se importa de `@laravel/echo-react` y se llama igual. `useEcho` se suscribe a un canal privado; para uno público existe `useEchoPublic`, y para uno de presencia, `useEchoPresence`.

## A producción

- Mantén Reverb vivo con Supervisor y ejecuta `php artisan reverb:restart` en cada deploy.
- Nginx hace de proxy inverso y expone Reverb por `wss`.
- Con más de unas 1.000 conexiones simultáneas necesitas la extensión `ext-uv` y subir el límite de archivos abiertos del sistema operativo.
- Para varios servidores, activa `REVERB_SCALING_ENABLED=true` y usa Redis, detrás de un balanceador.
- Pulse puede mostrar las conexiones y los mensajes de Reverb.

## Errores comunes

- Olvidar el worker de cola: el evento queda esperando y no llega nada.
- Un canal privado sin regla de autorización estricta.
- Poner datos sensibles en las propiedades públicas del evento: viajan al navegador.

## Practica

Haz el reto de este módulo: muestra en pantalla el progreso de un job con un evento `ImportProgressed`, un canal privado por usuario y un componente que lo escuche con `useEcho`.
