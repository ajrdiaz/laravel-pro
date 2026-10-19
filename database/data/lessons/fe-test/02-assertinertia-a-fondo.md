---
title: El contrato con Laravel: assertInertia a fondo
summary: Cómo comprobar desde Pest qué componente y qué props entrega cada ruta, por qué los ámbitos son estrictos, y cómo probar recargas parciales, props diferidas y mensajes flash.
minutes: 9
---

En una app Inertia, el backend y la página se hablan con **props**. Si el controlador cambia un nombre de campo, la página se rompe sin que nadie lo note. Una prueba de endpoint lo detecta en milisegundos, sin abrir un navegador.

## Lo básico

```php
use Inertia\Testing\AssertableInertia as Assert;

$this->actingAs($user)->get(route('stacks.index'))
    ->assertInertia(fn (Assert $page) => $page
        ->component('Stacks')
        ->has('stacks', 11)
        ->where('categories.0', ['value' => 'web', 'label' => 'Aplicaciones web']));
```

- `component` comprueba qué página se renderiza.
- `has('stacks', 11)` comprueba que la prop existe y tiene 11 elementos. Sin el número, funciona como `isset`.
- `where` compara un valor, con notación de puntos para entrar en lo anidado (`categories.0`).

Cuando falla, los mensajes dicen qué pasó:

```text
Unexpected Inertia page component.
Property [stacks.0.slug] does not match the expected value.
Property [stacks] does not have the expected size. Failed asserting that actual size 11 matches expected size 99.
```

## Ámbitos: entrar en una prop

Pasa una función a `has` para trabajar dentro de una prop. Si es una lista, el número comprueba el tamaño y la función entra en el **primer** elemento:

```php
->has('stacks', 11, fn (Assert $stack) => $stack
    ->where('slug', 'monolito-blade')
    ->where('category', 'web')
    ->hasAll(['name', 'summary', 'headline', 'reviewed_on']))
```

`hasAll` comprueba que existen varias claves a la vez. Y `missing` comprueba que una **no** está: es la forma de asegurar que no se filtran datos privados.

```php
->missing('password')
```

## Los ámbitos son estrictos

Esta es la trampa que más sorprende. Dentro de un ámbito, Laravel exige que hayas tocado **todas** las claves. Si solo compruebas una y la prop tiene más, falla:

```php
->has('stacks.0', fn (Assert $stack) => $stack->where('slug', 'monolito-blade'))
// Unexpected properties were found in scope [stacks.0].
```

El nivel raíz, en cambio, no es estricto. Hay dos salidas: comprobar el resto con `hasAll`, o decir explícitamente que no te importan con `etc()`:

```php
->has('stacks.0', fn (Assert $stack) => $stack->where('slug', 'monolito-blade')->etc())
```

Es una característica, no una molestia: si mañana alguien añade un campo al controlador, el test te obliga a decidir si debe estar ahí. Usa `hasAll` cuando quieras ese aviso y `etc()` cuando no.

## Coger los datos y comprobar a mano

Para comprobaciones que `where` no expresa bien, saca las props y úsalas como cualquier arreglo:

```php
$response = $this->get(route('stacks.compare', ['stacks' => ['rest-api', 'tiempo-real']]));

$columns = collect($response->inertiaProps('columns'));

$this->assertSame(['rest-api', 'tiempo-real'], $columns->pluck('slug')->all());
```

`inertiaProps()` devuelve todas las props, y con una clave (con puntos) solo esa.

## Recargas parciales

Esta app, tras responder una tarjeta, pide solo lo que cambió: `router.post(..., { only: ['stats'] })`. Eso es un **acuerdo** entre la página y el controlador, y se prueba con `reloadOnly`, que hace una segunda petición pidiendo solo esas props:

```php
$this->actingAs($user)->get(route('flashcards.index'))
    ->assertInertia(fn (Assert $page) => $page
        ->has('cards', 1)
        ->has('stats')
        ->reloadOnly('stats', fn (Assert $reload) => $reload
            ->has('stats')
            ->missing('cards')));
```

Si alguien cambia `stats` por otro nombre o deja de enviarlo en la recarga, el test falla en vez de dejar el contador de la barra lateral congelado. `reloadExcept` hace lo contrario: pide todo menos lo indicado.

## Props diferidas

Con `Inertia::defer`, una prop no viaja en la primera respuesta y se carga después. `loadDeferredProps` hace esa segunda petición:

```php
->missing('permissions')   // no está en la respuesta inicial
->loadDeferredProps(fn (Assert $reload) => $reload
    ->has('permissions')
    ->where('permissions.0.name', 'edit users'))
```

Puedes pasarle el nombre de un grupo para cargar solo ese.

## Mensajes flash

En una página renderizada, `hasFlash` y `missingFlash`. En una **redirección**, que es lo habitual tras un formulario, se comprueban en la respuesta con `assertInertiaFlash`. Así se prueba que «Borrar progreso» avisa:

```php
$this->actingAs($user)
    ->delete(route('progress.destroy'))
    ->assertRedirect(route('dashboard'))
    ->assertInertiaFlash('toast', ['type' => 'success', 'message' => 'Progreso borrado.'])
    ->assertInertiaFlashMissing('error');
```

## Qué no prueba esto

Ni una línea de JavaScript: no sabe si el botón se ve ni si el componente pinta bien la prop. Para eso están Vitest y Playwright. `assertInertia` garantiza el **contrato**, no la pantalla.

Dos detalles prácticos: la documentación recomienda `INERTIA_SSR_ENABLED=false` en `phpunit.xml` para que las pruebas no intenten renderizar en el servidor; y en esta app, si añades una página Vue nueva y el test falla con «Not a valid Inertia response», ejecuta `npm run build`: el manifest de Vite todavía no conoce esa página.

## Errores comunes

- Comprobar solo `component` y nunca las props: el test pasa aunque el controlador mande basura.
- Sorprenderse por «Unexpected properties were found in scope»: es el ámbito estricto, usa `hasAll` o `etc()`.
- No probar con `missing` que no se envían campos privados.
- Dar por probada la interfaz solo porque `assertInertia` pasa.
- Una recarga parcial en la página sin un test que fije qué props necesita.

## Practica

Escribe un test de la página `Flashcards` con `has`, `hasAll` en el primer elemento de `cards` y `missing('answer_hash')`. Añade un campo cualquiera a la tarjeta en el controlador y mira cómo falla el ámbito estricto. Después arréglalo de las dos formas, con `hasAll` y con `etc()`, y decide cuál preferirías si el campo fuera sensible.
