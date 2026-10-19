---
title: Inertia en 15 minutos
summary: Cómo un controlador de Laravel entrega props a una página Vue o React, y cómo manejar formularios, datos compartidos y recargas parciales.
minutes: 9
---

Inertia conecta tus controladores de Laravel con páginas Vue o React, sin construir una API aparte. Sigues usando rutas, validación y sesión de Laravel, pero la interfaz se comporta como una SPA.

## El controlador entrega props

```php
public function index(Request $request): Response
{
    return Inertia::render('Tasks/Index', [
        'tasks' => $request->user()->tasks()->latest()->get(['id', 'title', 'done']),
    ]);
}
```

`Inertia::render()` recibe el nombre de la página y sus props. Todo lo que pases llega al navegador, así que **nunca** pases un modelo entero con campos que no quieras exponer: elige las columnas.

## La página recibe las props

```vue
<script setup lang="ts">
defineProps<{
    tasks: { id: number; title: string; done: boolean }[];
}>();
</script>

<template>
    <ul>
        <li v-for="task in tasks" :key="task.id">{{ task.title }}</li>
    </ul>
</template>
```

Esta página vive en `resources/js/pages/Tasks/Index.vue`. En React la idea es idéntica: el componente recibe `{ tasks }` como parámetro. El controlador no cambia al pasar de Vue a React.

## Formularios con errores de validación

```vue
<script setup lang="ts">
import { useForm } from '@inertiajs/vue3';

const form = useForm({ title: '' });
</script>

<template>
    <form @submit.prevent="form.post('/tasks')">
        <input v-model="form.title" />
        <p v-if="form.errors.title">{{ form.errors.title }}</p>
        <button :disabled="form.processing">Guardar</button>
    </form>
</template>
```

Si la validación falla, Laravel redirige atrás y Inertia entrega los errores en `form.errors`. `form.processing` evita el doble envío. Esta app usa además el componente `<Form>` junto con Wayfinder; mira `resources/js/pages/settings/Profile.vue`.

## Datos compartidos

Lo que necesitan todas las páginas (el usuario, mensajes, contadores) va en `HandleInertiaRequests::share()`. En esta app comparte `auth`, `name` y `stats`, y de ahí sale el número de tarjetas pendientes del menú lateral.

## Recargas parciales

```ts
router.reload({ only: ['tasks'] });
```

Pide de nuevo solo esa prop, sin recargar el resto. En `resources/js/pages/Flashcards.vue` cada respuesta se registra con `only: ['stats']`, así el menú se actualiza sin reiniciar la cola de tarjetas que tienes en pantalla.

## Cuándo elegirlo

Inertia encaja cuando un solo equipo hace backend y frontend y hay un único cliente web. Si habrá una app móvil o integraciones de terceros, necesitas una API de todos modos. La ficha [Inertia + Vue o React](/stacks/inertia-vue-react), en la sección de Stacks, lo compara a fondo.

## Errores comunes

- Pasar como props modelos completos con datos que el usuario no debe ver.
- Hacer `fetch` a mano en lugar de usar `router` o `useForm`, y perder el manejo de errores y de redirecciones.
- Olvidar `preserveScroll` en acciones que no deberían mover la página.

## Practica

Haz el reto de este módulo: rehaz en React una página que ya tengas en Vue con el mismo controlador, con `useForm`, errores de validación y una recarga parcial.
