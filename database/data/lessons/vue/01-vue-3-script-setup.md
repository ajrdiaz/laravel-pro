---
title: Vue 3 con script setup
summary: ref, computed, watch, props, v-model y composables, construyendo una lista de tareas paso a paso.
minutes: 9
---

Vue te deja describir la interfaz como una función de los datos: cambias los datos y la pantalla se actualiza sola. Esta lección construye una lista de tareas con lo esencial de la API de composición.

## Un componente completo

```vue
<script setup lang="ts">
import { computed, ref } from 'vue';

type Task = { id: number; title: string; done: boolean };

const tasks = ref<Task[]>([]);
const title = ref('');

const pending = computed(() => tasks.value.filter((task) => !task.done).length);

function add() {
    if (title.value.trim() === '') {
        return;
    }

    tasks.value.push({ id: Date.now(), title: title.value, done: false });
    title.value = '';
}
</script>

<template>
    <form @submit.prevent="add">
        <input v-model="title" placeholder="Nueva tarea" />
        <button>Añadir</button>
    </form>

    <p>{{ pending }} pendientes</p>

    <ul>
        <li v-for="task in tasks" :key="task.id">
            <label>
                <input v-model="task.done" type="checkbox" />
                {{ task.title }}
            </label>
        </li>
    </ul>
</template>
```

Un archivo `.vue` junta la lógica (`<script setup>`) y la vista (`<template>`).

## Reactividad

- **`ref()`** guarda un valor reactivo. En el script se lee y se escribe con `.value`; en el template Vue lo desenvuelve solo.
- **`computed()`** deriva un valor de otros y lo recalcula únicamente cuando cambian sus dependencias. `pending` se actualiza solo al completar una tarea.
- **`watch()`** ejecuta un efecto cuando algo cambia: guardar en `localStorage`, llamar a una API.

```ts
watch(tasks, (value) => localStorage.setItem('tasks', JSON.stringify(value)), { deep: true });
```

Regla práctica: si el resultado es un **valor**, usa `computed`. Si es una **acción**, usa `watch`.

## v-for y la key

`:key="task.id"` permite a Vue identificar cada elemento al reordenar o filtrar. Usa un id estable. El índice del arreglo solo sirve si la lista nunca cambia de orden.

`v-if` agrega o quita el elemento del DOM; `v-show` lo deja y lo oculta con CSS. Usa `v-show` para lo que alterna a menudo.

## Props, eventos y v-model

Las props bajan datos al hijo y los eventos suben al padre. `v-model` es el atajo de las dos cosas. En esta app, `resources/js/components/study/ModuleSelect.vue` lo hace con `defineModel`:

```vue
<script setup lang="ts">
const model = defineModel<string>({ required: true });
</script>
```

Y quien lo usa escribe simplemente `<ModuleSelect v-model="selectedModule" />`.

## Composables: lógica reutilizable

Cuando una lógica con estado se repite, extráela a una función `useAlgo()`:

```ts
// resources/js/composables/useTasks.ts
import { computed, ref } from 'vue';

export type Task = { id: number; title: string; done: boolean };

export function useTasks() {
    const tasks = ref<Task[]>([]);
    const pending = computed(() => tasks.value.filter((task) => !task.done).length);

    function add(title: string) {
        tasks.value.push({ id: Date.now(), title, done: false });
    }

    return { tasks, pending, add };
}
```

El componente solo hace `const { tasks, pending, add } = useTasks();`. Un ejemplo real del proyecto es `resources/js/composables/useAppearance.ts`.

## Errores comunes

- Olvidar `.value` en el script y que nada cambie.
- Usar `watch` para calcular un valor derivado. Es un `computed`.
- Una `:key` basada en `Math.random()`: Vue recrea todos los elementos en cada render.
- Mutar una prop dentro del hijo. Emite un evento o usa `v-model`.

## Practica

Haz el reto de este módulo: la lista de tareas con filtro, contador y la lógica extraída a un composable `useTasks()`.
