---
title: Componentes que se componen: defineModel, eventos tipados y slots
summary: Cómo construir componentes reutilizables con v-model sin código repetido, eventos con tipos, slots con datos y referencias al DOM.
minutes: 8
---

Un componente reutilizable se parece a una función: recibe cosas por props, avisa por eventos y deja huecos con slots. Vue 3.5 (la versión de esta app) lo hace con poco código si usas las herramientas adecuadas.

## v-model con defineModel

Antes, un componente con `v-model` necesitaba una prop `modelValue` y un evento `update:modelValue`. Desde Vue 3.4, `defineModel()` lo reduce a una línea. Esta app lo usa en `ModuleSelect.vue`:

```vue
<script setup lang="ts">
const model = defineModel<string>({ required: true });
</script>

<template>
    <Select v-model="model">...</Select>
</template>
```

`model` es una referencia que puedes leer y asignar: al asignarla, Vue emite el evento por ti. El padre lo usa como cualquier `v-model`:

```vue
<ModuleSelect v-model="selectedModule" ... />
```

`defineModel` acepta opciones como `required: true` o `default: false`. Y si necesitas más de un valor, les das nombre: `defineModel('title')` y el padre escribe `v-model:title="..."`.

## Eventos con tipos

Los eventos que no son `v-model` se declaran con `defineEmits`. Con TypeScript, la sintaxis corta (Vue 3.3+) usa tuplas con nombre:

```ts
const emit = defineEmits<{
    toggled: [open: boolean];
    removed: [id: number, reason: string];
}>();

emit('toggled', true);
emit('removed', 5); // error: falta el segundo argumento
```

El padre queda comprobado también: si escribe `@toggled="(open: string) => ..."`, el compilador avisa de que el evento entrega un `boolean`.

## Slots: huecos con contenido del padre

Un slot deja que quien usa el componente decida qué se pinta dentro:

```vue
<!-- Panel.vue -->
<script setup lang="ts">
const open = defineModel<boolean>({ default: false });

defineSlots<{
    default(): unknown;
    header(props: { open: boolean }): unknown;
}>();
</script>

<template>
    <section>
        <button type="button" :aria-expanded="open" @click="open = !open">
            <slot name="header" :open="open">Detalles</slot>
        </button>
        <div v-show="open"><slot /></div>
    </section>
</template>
```

- `<slot />` es el hueco principal.
- `<slot name="header">` es un hueco con nombre. El texto «Detalles» es el **contenido por defecto**, que se muestra si el padre no rellena ese hueco.
- `:open="open"` convierte el slot en uno **con datos** (*scoped slot*): el padre recibe `open` y decide cómo pintarlo.

El padre rellena los huecos con `#nombre`:

```vue
<Panel v-model="show">
    <template #header="{ open }">{{ open ? 'Ocultar' : 'Mostrar' }} requisitos</template>
    Aquí va el contenido principal.
</Panel>
```

`defineSlots` (Vue 3.3+) solo da pistas de tipos al editor: con él, `#header="{ nope }"` falla con `Property 'nope' does not exist on type '{ open: boolean; }'`.

## Referencias al DOM con useTemplateRef

A veces hay que tocar un elemento: enfocar un campo, hacer scroll. Desde Vue 3.5 se hace con `useTemplateRef`. El asistente de stacks de esta app lo usa para llevarte al resultado:

```vue
<script setup lang="ts">
import { nextTick, useTemplateRef } from 'vue';

const results = useTemplateRef<HTMLElement>('results');

function showResults() {
    nextTick(() => results.value?.scrollIntoView({ block: 'start' }));
}
</script>

<template>
    <section v-if="result" ref="results">...</section>
</template>
```

El nombre de `useTemplateRef` debe coincidir con el atributo `ref` del elemento. La referencia vale `null` hasta que el componente se monta, o mientras el `v-if` oculte el elemento, por eso el `?.`. Y `nextTick` espera a que Vue termine de actualizar el DOM antes de usarlo.

## Errores comunes

- Mutar una prop directamente (`props.items.push(...)`): usa `defineModel` o emite un evento.
- Usar el índice de `v-for` como `key` en una lista que se reordena.
- Acceder a una referencia de plantilla antes de montar el componente, o con un `v-if` que aún no se cumple.
- Un componente con diez props booleanas que cambian su aspecto: probablemente necesitaba un slot.
- Olvidar el contenido por defecto de un slot que casi siempre se deja vacío.

## Practica

Crea un componente `Collapsible` con `defineModel` para el estado abierto o cerrado, un slot `header` con datos y un evento `toggled`. Úsalo en la página de un proyecto para plegar la sección «Para ir más lejos». Después escribe un uso incorrecto a propósito (otro tipo en el `v-model`, un slot prop que no existe) y comprueba qué errores da `npm run types:check`.
