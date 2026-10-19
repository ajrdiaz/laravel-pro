---
title: Navegación, layouts y props diferidas en Inertia
summary: Cómo navegar sin recargar, cómo se eligen los layouts y cómo cargar después lo que tarda en calcularse.
minutes: 8
---

La primera lección de Inertia cubría props y formularios. Esta explica por qué una app Inertia se **siente** como una SPA y cómo mantenerla ágil.

## Navegar sin recargar

```vue
<script setup lang="ts">
import { Link, router } from '@inertiajs/vue3';
</script>

<template>
    <Link href="/tasks">Tareas</Link>
    <Link href="/tasks" preserve-scroll>Tareas</Link>
    <button @click="router.visit('/tasks')">Ir</button>
</template>
```

Un `<Link>` se comporta como un enlace normal para el usuario (y para los buscadores), pero Inertia lo intercepta, pide solo los datos de la página nueva y cambia el componente sin recargar el navegador. No se pierde el estado de la barra lateral ni se vuelve a cargar el JavaScript.

Dos opciones que importan mucho:

- **`preserve-scroll`**: no vuelve arriba. Imprescindible en acciones que no deberían mover la página, como marcar un concepto.
- **`preserve-state`**: conserva el estado local de los componentes. Esta app lo usa en `CompletionToggle.vue` y en las tarjetas: al responder una, la cola de tarjetas que tienes en pantalla no se reinicia.

Un enlace a otra página de **tu** app debe ser un `<Link>`. Un `<a href>` normal funciona, pero hace una carga completa de la página y pierdes todo lo anterior.

## Layouts: cómo se elige uno

Un layout es el marco que rodea a una página (barra lateral, cabecera). En esta app se elige en `resources/js/app.ts` según el nombre de la página:

```ts
layout: (name) => {
    switch (true) {
        case name.startsWith('auth/'):
            return AuthLayout;
        case name.startsWith('settings/'):
            return [AppLayout, SettingsLayout];
        default:
            return AppLayout;
    }
},
```

Una página puede además declarar datos para su layout, como las migas de pan:

```ts
defineOptions({
    layout: {
        breadcrumbs: [{ title: 'Ruta', href: dashboard() }],
    },
});
```

La ventaja de los **layouts persistentes** es que el layout no se destruye al navegar entre dos páginas que lo comparten: la barra lateral conserva su estado y su posición de scroll.

## Props diferidas: lo caro, después

Imagina una página cuya mitad de datos se calcula en 2 segundos. Sin más, el usuario espera 2 segundos viendo una pantalla en blanco. Con `Inertia::defer()` la página aparece al instante y lo lento llega después:

```php
return Inertia::render('Users/Index', [
    'users' => User::all(),
    'permissions' => Inertia::defer(fn () => Permission::all()),
]);
```

El callback se ejecuta en una **petición aparte** tras el primer render. Tu página debe estar preparada para que esa prop todavía no exista: muestra un esqueleto o un indicador de carga mientras tanto. Si hay varias props diferidas, por defecto se piden en una sola petición; puedes agruparlas para pedirlas en paralelo.

Es la herramienta correcta cuando una parte secundaria de la página es lenta. Si lo lento es la parte principal, arregla la consulta.

## Mensajes de una sola vez

Para un aviso tras una acción («Progreso borrado»), Inertia 3 tiene `Inertia::flash()`:

```php
Inertia::flash('toast', ['type' => 'success', 'message' => 'Progreso borrado.']);

return to_route('dashboard');
```

El dato viaja con la siguiente respuesta y desaparece. Así lo usa `ProgressController` en esta app, y un pequeño código en el frontend (`resources/js/lib/flashToast.ts`) lo muestra como notificación.

## Errores comunes

- Un `<a href>` a una página propia: recarga todo y pierdes el estado.
- No usar `preserve-scroll` en una acción de la página: salta arriba.
- Una prop diferida sin estado de carga: el usuario ve un hueco o un error.
- Pasar a una prop el modelo completo cuando la página solo necesita tres campos.

## Practica

Toma una página tuya con una consulta lenta secundaria, conviértela en una prop diferida con un esqueleto de carga y comprueba en la pestaña de red que la página aparece antes de recibir los datos.
