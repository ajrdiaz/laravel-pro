---
title: TypeScript, lo justo para un proyecto Inertia
summary: Tipos de props, uniones, narrowing, unknown y genéricos, con ejemplos de esta misma app.
minutes: 8
---

TypeScript no hace tu código más rápido: lo hace más difícil de romper por accidente. El editor te avisa de un campo mal escrito mientras tecleas, y `npm run types:check` lo comprueba en toda la app antes de desplegar.

## Describir la forma de los datos

```ts
type Stats = {
    progress: number;
    due_cards: number;
    streak: number;
};
```

Esa definición está en `resources/js/types/study.ts` y refleja exactamente lo que envía `ProgressReport::stats()` desde Laravel. Los tipos del frontend son el contrato con el controlador: si el backend cambia un campo, `types:check` te enseña qué componentes dejaron de cuadrar.

Los tipos se usan para las props de una página o de un componente:

```vue
<script setup lang="ts">
import type { RoadmapModule } from '@/types';

defineProps<{
    modules: RoadmapModule[];
}>();
</script>
```

Un campo opcional se marca con `?`: `{ id: number; name?: string }` significa que `name` puede faltar.

## Uniones: solo estas opciones

```ts
type Status = 'idle' | 'loading' | 'error';

function label(status: Status): string {
    switch (status) {
        case 'idle':
            return 'Listo';
        case 'loading':
            return 'Cargando…';
        case 'error':
            return 'Algo falló';
    }
}
```

Si añades `'success'` a `Status` y olvidas manejarlo, TypeScript marca el error en `label`. Un `string` suelto no te avisaría de nada.

Una **unión discriminada** añade a cada variante un campo con valor literal, y al comprobarlo TypeScript sabe qué más hay:

```ts
type Result<T> =
    | { status: 'ok'; data: T }
    | { status: 'error'; message: string };

function show(result: Result<Stats>) {
    if (result.status === 'ok') {
        return result.data.progress;   // aquí `data` existe
    }

    return result.message;             // y aquí `message`
}
```

## unknown: lo que viene de fuera

```ts
function parse(raw: unknown): Stats | null {
    if (
        typeof raw === 'object' &&
        raw !== null &&
        'progress' in raw &&
        typeof raw.progress === 'number'
    ) {
        return raw as Stats;
    }

    return null;
}
```

`unknown` te obliga a comprobar antes de usar. `any` apaga el compilador y lo vuelve todo posible hasta que falla en ejecución. Recuerda que **los tipos se borran al compilar**: TypeScript no garantiza que el servidor cumpla lo que declaraste. Con Inertia el riesgo es menor porque el controlador lo controlas tú, pero con una API de terceros hay que validar.

## Genéricos

```ts
function first<T>(items: T[]): T | undefined {
    return items[0];
}

const module = first(modules);   // RoadmapModule | undefined
```

Los genéricos reutilizan una función o un tipo con distintos contenidos. Ya los usas sin darte cuenta: `Array<T>`, `Promise<T>` y `ref<Task[]>([])`.

## La comprobación en el proyecto

```bash
npm run types:check
```

Ejecuta `vue-tsc --noEmit`, el compilador de TypeScript que entiende archivos `.vue`, y solo comprueba los tipos. También forma parte de `composer ci:check`, así que conviene que corra en tu pipeline.

## Errores comunes

- Usar `any` «para salir del paso». Casi siempre `unknown` y una comprobación resuelve el problema.
- Una aserción `as Tipo` para callar al compilador: no comprueba nada, solo miente.
- No tipar las props de una página y descubrir un campo mal escrito en producción.

## Practica

Haz el reto de este módulo: tipa de punta a punta un formulario de Inertia (props de la página, datos del formulario y una unión con los estados de carga) y comprueba que `npm run types:check` falla si olvidas una prop.
