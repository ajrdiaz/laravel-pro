---
title: Tailwind CSS sin caos
summary: Utilidades, estados, diseño responsive y modo oscuro, y cómo evitar que las clases se vuelvan un enredo.
minutes: 7
---

Tailwind te deja dar estilo escribiendo clases pequeñas en el propio HTML, en lugar de inventar un nombre de clase y un archivo CSS para cada cosa. Funciona muy bien si le sumas una disciplina: componentes y un tema.

## Utilidades

```html
<article class="flex flex-col gap-3 rounded-xl border bg-card p-4">
    <h2 class="text-lg font-semibold">Título</h2>
    <p class="text-muted-foreground">Descripción</p>
</article>
```

Cada clase hace una sola cosa: `flex` activa flexbox, `gap-3` separa los hijos, `p-4` da relleno, `rounded-xl` redondea las esquinas. Los valores salen de una escala de espaciado compartida, así que todo queda consistente sin pensar en píxeles.

Los colores `bg-card`, `border` y `text-muted-foreground` no son colores fijos: son los nombres del tema de los starter kits, definidos como variables CSS en `resources/css/app.css`. Cambiar el tema en un solo lugar cambia toda la app, incluido el modo oscuro.

## Estados y tamaños con prefijos

```html
<a class="rounded-md border p-4 hover:border-primary focus-visible:ring-[3px] md:p-6">
```

- `hover:` y `focus-visible:` aplican la utilidad solo en ese estado. Usa siempre un estilo de foco visible: lo necesita quien navega con teclado.
- `md:` aplica desde el ancho mediano hacia arriba. Tailwind es **mobile-first**: lo que escribes sin prefijo vale para todo, y los prefijos lo modifican en pantallas mayores.

Una rejilla que pasa de una columna a tres:

```html
<div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
```

## Modo oscuro

```html
<div class="bg-white text-black dark:bg-black dark:text-white">
```

En esta app, `app.css` declara `@custom-variant dark (&:is(.dark *));`, de modo que `dark:` se activa cuando `<html>` tiene la clase `dark`. Si usas los nombres del tema (`bg-card`, `text-foreground`), casi nunca necesitas escribir `dark:` a mano: la variable cambia sola.

## Componentes, no @apply

Cuando repites las mismas diez clases, la solución no es una clase CSS con `@apply`: es un **componente**. Mira `resources/js/components/study/ProgressBar.vue`: contiene sus clases una sola vez y todo el que lo usa las hereda.

Para clases condicionales, los proyectos con shadcn tienen una función `cn()` en `@/lib/utils`:

```ts
import { cn } from '@/lib/utils';

const classes = cn('px-4 py-2', isActive && 'bg-primary text-primary-foreground', props.class);
```

`cn()` combina `clsx` (que junta clases condicionales) con `tailwind-merge` (que resuelve conflictos: si pasas `p-2` y `p-4`, se queda con `p-4`). Así un componente puede aceptar una clase de fuera y sobrescribir la suya sin sorpresas.

## shadcn: componentes que son tuyos

shadcn-vue y shadcn/ui no son una librería que importas: son componentes accesibles que se **copian** a tu proyecto (`resources/js/components/ui/`) y los modificas como código propio. Por eso el starter kit trae `Button`, `Dialog` o `Select` ya hechos, con foco y teclado bien resueltos.

## Orden de las clases

El formateador de esta app (la opción `sortTailwindcss` de `vite.config.ts`) ordena las clases de Tailwind automáticamente al ejecutar `npm run check:fix`. Así dos personas escriben las mismas clases en el mismo orden y los diffs se leen mejor.

## Errores comunes

- Crear clases a mano (`.card-title`) para cosas que ya resuelve una utilidad.
- Pensar en escritorio primero y luego «arreglar» el móvil.
- Colores fijos (`bg-white`) en lugar de los del tema: el modo oscuro deja de funcionar.
- Quitar el estilo de foco para que «se vea más limpio».

## Practica

Haz el reto de este módulo: una tarjeta de producto responsive con modo oscuro, hecha solo con utilidades y un componente `ProductCard`, comprobando el contraste y el foco con el teclado.
