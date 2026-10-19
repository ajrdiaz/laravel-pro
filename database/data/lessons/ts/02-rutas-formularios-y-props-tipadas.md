---
title: Tipar las fronteras: rutas, formularios y props compartidas
summary: Dónde TypeScript te salva de verdad en una app Inertia: rutas de Wayfinder, formularios, props compartidas y uniones que no se olvidan de ningún caso.
minutes: 8
---

TypeScript rinde más en las **fronteras**, donde tu código habla con algo que no controla: las rutas del servidor, los formularios, las props que llegan de Laravel. Ahí un error de escritura suele pasar desapercibido hasta producción. Con tipos, lo ves al teclearlo.

## Rutas que el compilador conoce

Wayfinder genera una función por cada ruta de Laravel (`php artisan wayfinder:generate --with-form`). Sus argumentos están tipados:

```ts
import { update } from '@/routes/notes';

update('php');             // bien: el slug del módulo
update({ slug: 'php' });   // bien
update(42);                // error
update({ nope: 'x' });     // error
```

Los dos últimos fallan al comprobar tipos:

```text
error TS2345: Argument of type 'number' is not assignable to parameter of type
'string | { module: string | { slug: string; }; } | [module: ...] | { slug: string; }'.

error TS2353: Object literal may only specify known properties,
and 'nope' does not exist in type ...
```

Si renombras un parámetro de ruta en Laravel y regeneras, cada llamada que lo pasaba **por nombre** (`lesson({ module: 'php', lesson: 'x' })`) se pone en rojo. Sin esto, lo descubrirías con un 404.

## Formularios tipados

`useForm` acepta un genérico con la forma de los datos, y los errores heredan sus campos:

```ts
const form = useForm<{ name: string; email: string }>({ name: '', email: '' });

form.name = 'Ana';
form.nmae = 'typo';          // Property 'nmae' does not exist
form.errors.emial;           // Did you mean 'email'?
```

`useHttp`, el hook para peticiones que no navegan (como el guardado de notas de esta app), recibe **dos** genéricos: los datos que envías y, opcionalmente, la respuesta:

```ts
const http = useHttp<{ body: string }, { saved: boolean }>(
    () => update(props.moduleSlug),
    { body: props.note ?? '' },
);
```

Para el componente `<Form>`, en Vue creas una versión tipada con `createForm<MisDatos>()`, y los `errors` de sus slot props quedan comprobados.

## Props compartidas

Los datos que Laravel comparte con **todas** las páginas (el usuario, las estadísticas) se declaran una vez, ampliando `InertiaConfig`. Esta app lo hace en `resources/js/types/global.d.ts`:

```ts
import type { Auth } from '@/types/auth';
import type { Stats } from '@/types/study';

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            stats: Stats | null;
            sidebarOpen: boolean;
        };
    }
}
```

Con eso, `usePage().props.stats?.due_cards` tiene tipo en cualquier componente. Dos detalles de la documentación de Inertia: el archivo necesita un `import` (o `export {}`) para ser un módulo, o `declare module` **reemplaza** el módulo en vez de ampliarlo; y el `tsconfig.json` debe incluir los `.d.ts`.

Las props de una página concreta se tipan con un genérico, y se combinan con las compartidas:

```ts
const page = usePage<{ posts: { id: number; title: string }[] }>();
```

## Uniones que no olvidan ningún caso

Cuando un valor tiene pocas opciones, usa una unión y obliga al compilador a revisarlas todas:

```ts
type Status = 'ready' | 'learning' | 'todo';

function label(status: Status): string {
    switch (status) {
        case 'ready':
            return 'Listo';
        case 'learning':
            return 'En camino';
        default: {
            const unreachable: never = status;

            return unreachable;
        }
    }
}
```

Aquí falta `'todo'`, y el compilador lo dice: `Type '"todo"' is not assignable to type 'never'`. El día que añadas un cuarto estado, todos los `switch` que no lo manejen fallarán a la vez.

## El contrato con Laravel

Los tipos del frontend se escriben a mano en `resources/js/types/` y reflejan lo que envía el controlador. TypeScript **no valida los datos en tiempo de ejecución**: si el backend cambia un campo y no actualizas el tipo, el compilador sigue contento. Por eso las pruebas con `assertInertia` en el backend y `npm run types:check` en el frontend se complementan: una fija la forma de lo que se envía, la otra comprueba cómo se usa. La documentación de Inertia indica que una versión futura de Wayfinder podrá generar estos tipos automáticamente; aún está en beta.

## Errores comunes

- Usar `any` o `as` para callar un error: apagas la comprobación justo donde era útil.
- Declarar `sharedPageProps` en un `.d.ts` sin `import` ni `export {}`.
- Un tipo del frontend que nadie actualiza cuando cambia el controlador.
- Marcar todo como opcional (`?`) para evitar errores: el tipo deja de decir nada.

## Practica

En `routes/web.php`, cambia el parámetro `{lesson:slug}` de la ruta `learn.lesson` por `{page:slug}` (y el parámetro del método del controlador), ejecuta `php artisan wayfinder:generate --with-form` y mira qué archivos rompe `npm run types:check`. Luego deshaz el cambio. Después añade un cuarto estado a `JobModule['status']` y deja que el compilador te señale qué `switch` o qué objeto de etiquetas hay que completar.
