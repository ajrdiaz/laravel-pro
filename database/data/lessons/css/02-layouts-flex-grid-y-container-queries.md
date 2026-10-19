---
title: Layouts con Flexbox, Grid y container queries
summary: Cuándo usar flex y cuándo grid, cómo se comporta el diseño responsive de Tailwind, por qué un texto largo rompe un flex y cómo escribir clases que Tailwind encuentre.
minutes: 8
---

La mayor parte del CSS que escribirás es **colocar cosas**: una fila, una rejilla, una columna que se apila en el móvil. Dos herramientas cubren casi todo: flexbox y grid.

## Flex: una dimensión

Flex coloca elementos en **una** fila o columna. Es lo que usarás para barras, filas de botones y tarjetas con contenido a un lado y una etiqueta al otro:

```html
<div class="flex flex-wrap items-center justify-between gap-3">
    <h2 class="font-semibold">Tarjetas del módulo</h2>
    <a class="shrink-0" href="/flashcards/export">Exportar a Anki</a>
</div>
```

- `gap-3` separa los hijos sin márgenes sueltos.
- `items-center` alinea en el eje transversal; `justify-between` reparte el espacio en el principal.
- `flex-wrap` deja que los hijos pasen a otra línea si no caben: así la fila se adapta al móvil sin media queries.
- `shrink-0` impide que un elemento se encoja.

## Grid: dos dimensiones

Grid reparte el espacio en **filas y columnas** a la vez. Para una rejilla que se adapta:

```html
<div class="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
    <!-- tarjetas -->
</div>
```

En un móvil son tarjetas apiladas (una columna implícita); desde `sm`, dos; desde `lg`, tres. La página de comparar stacks de esta app usa `grid gap-3 sm:grid-cols-3` para sus tres selectores.

## Mobile-first: qué significa `sm:`

Las utilidades sin prefijo valen para **todos** los tamaños. Un prefijo como `md:` significa «de este ancho **en adelante**», no «solo en este tamaño». Los puntos de corte por defecto:

| Prefijo | Ancho mínimo |
| --- | --- |
| `sm` | 40rem (640px) |
| `md` | 48rem (768px) |
| `lg` | 64rem (1024px) |
| `xl` | 80rem (1280px) |
| `2xl` | 96rem (1536px) |

El error clásico es escribir `sm:text-center` esperando que afecte al móvil. Primero diseña el móvil sin prefijos y añade cambios hacia arriba con `sm:`, `md:`, `lg:`.

## El texto largo que rompe un flex

Un hijo de flex (o de grid) no se encoge por debajo del tamaño de su contenido: su ancho mínimo automático es el del contenido sin partir. Un título largo, un correo o una ruta sin espacios empujan la fila y provocan desbordamiento. La solución es permitirle encogerse con `min-w-0`, y decidir qué hacer con el texto que sobra:

```html
<div class="flex items-start gap-3">
    <div class="min-w-0">
        <p class="truncate">{{ module.title }}</p>
        <p class="line-clamp-2">{{ module.summary }}</p>
    </div>
    <span class="shrink-0">12%</span>
</div>
```

`truncate` corta con puntos suspensivos en una línea; `line-clamp-2` lo hace tras dos líneas. Esta app usa `min-w-0` en las tarjetas de módulo de las fichas de stacks y proyectos.

## Tablas anchas

Una tabla con muchas columnas no cabe en un móvil. En vez de romper la página, deja que **ella** se desplace:

```html
<div class="overflow-x-auto rounded-xl border">
    <table class="w-full min-w-[40rem]">...</table>
</div>
```

El contenedor con `overflow-x-auto` hace scroll horizontal y la página no se ensancha. Así funciona el comparador de stacks.

## Container queries: responder al contenedor

`md:` mira el ancho de la **pantalla**. Pero un componente puede estar en una columna estrecha o en una ancha, con la misma pantalla. Tailwind lo resuelve con container queries: marcas el contenedor con `@container` y usas variantes como `@md:` dentro:

```html
<div class="@container">
    <div class="flex flex-col @md:flex-row">...</div>
</div>
```

La tarjeta se pone en fila cuando **su contenedor** mide al menos `@md`, esté donde esté. Suele ser la mejor opción para componentes reutilizables.

## Estados que dependen de los hijos

Con `has-*` estilas un elemento según lo que contiene. El asistente de stacks lo usa para resaltar la opción marcada:

```html
<label class="rounded-lg border has-[:checked]:border-primary has-[:checked]:bg-accent">
    <input type="radio" />
    Opción
</label>
```

Y `group-hover` o `peer-*` sirven para reaccionar a un padre o a un hermano marcado con `group` o `peer`.

## Movimiento y foco

Respeta a quien pide menos animación con `motion-reduce:transition-none`, y no quites nunca el indicador de foco: usa `focus-visible:ring-*` para que se vea al navegar con teclado.

## Clases completas, siempre

Tailwind lee tu código buscando **nombres de clase completos**. Si los construyes por partes, no los encuentra y no genera el CSS:

```html
<!-- no funciona: la clase no existe en el código -->
<div class="text-{{ error ? 'red' : 'green' }}-600">

<!-- funciona -->
<div class="{{ error ? 'text-red-600' : 'text-green-600' }}">
```

## Errores comunes

- Usar `sm:` esperando que afecte solo al móvil.
- Un texto largo sin `min-w-0` dentro de un flex: la fila se desborda.
- Envolver con `overflow-x-auto` solo parte de una tabla, o ponerlo en el elemento equivocado.
- Montar nombres de clase con variables (`bg-${color}-500`).
- Poner `outline-none` sin dar un estilo de foco alternativo.

## Practica

Crea una tarjeta de módulo con título largo, resumen y un porcentaje a la derecha. Pruébala con un título de 80 caracteres sin espacios a 390px de ancho: primero rompe el diseño, luego arréglalo con `min-w-0` y `truncate`. Después mete la tarjeta en un contenedor `@container` y haz que pase de columna a fila con `@md:`, comprobando que responde al contenedor y no a la ventana.
