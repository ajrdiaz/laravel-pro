---
title: Probar el frontend sin volverte loco
summary: Qué probar con Vitest, qué con Playwright, y cómo escribir tests que no se rompen por cambiar un color.
minutes: 8
---

Probar el frontend tiene mala fama porque casi todos empiezan probando lo equivocado: clases CSS, estructura del DOM, detalles de implementación. Un buen test dice lo que **ve y hace una persona**.

## La pirámide, versión corta

- **Muchas pruebas pequeñas y rápidas** de lógica y de componentes (Vitest).
- **Unas pocas pruebas de punta a punta** de los flujos que no pueden fallar: registrarse, pagar (Playwright).
- **Las pruebas del backend** en Pest: en una app Inertia, `assertInertia` comprueba qué componente y qué props devuelve cada ruta.

Cada capa prueba lo que mejor sabe probar. Si una prueba E2E comprueba un `if`, sobra una prueba unitaria.

## Componentes con Vitest y Testing Library

```bash
npm install -D vitest @testing-library/vue @testing-library/user-event
```

```ts
import { render, screen } from '@testing-library/vue';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';
import TaskList from './TaskList.vue';

describe('TaskList', () => {
    it('añade una tarea', async () => {
        const user = userEvent.setup();
        render(TaskList);

        await user.type(screen.getByRole('textbox'), 'Aprender Vitest');
        await user.click(screen.getByRole('button', { name: 'Añadir' }));

        expect(await screen.findByText('Aprender Vitest')).toBeTruthy();
    });
});
```

(Para React, el equivalente es `@testing-library/react`.)

La clave es cómo se busca el elemento: **por rol y nombre accesible**. `getByRole('button', { name: 'Añadir' })` encuentra el botón como lo encontraría un lector de pantalla. Si cambias los estilos, el test sigue pasando. Y si no logras encontrar un elemento por su rol, probablemente tampoco sea accesible.

Evita `.btn.btn-primary:nth-child(3)` o buscar por clases: cada refactor de CSS rompería el test sin que nada haya fallado.

## Flujos completos con Playwright

```bash
npm init playwright@latest
```

```ts
import { expect, test } from '@playwright/test';

test('un usuario inicia sesión y ve su ruta', async ({ page }) => {
    await page.goto('/login');
    await page.getByLabel('Email address').fill('test@example.com');
    await page.getByLabel('Password', { exact: true }).fill('password');
    await page.getByRole('button', { name: 'Log in' }).click();

    await expect(page).toHaveURL(/dashboard/);
    await expect(page.getByRole('heading', { name: 'Ruta Laravel' })).toBeVisible();
});
```

Se ejecuta con `npx playwright test`. Ajusta las etiquetas a las de tu propio formulario.

## Auto-waiting: adiós a los sleeps

Antes de hacer clic o escribir, Playwright **espera a que el elemento exista, sea visible y esté habilitado**. Y `expect(locator)` reintenta hasta que se cumple o vence el tiempo. Por eso no necesitas pausas fijas:

```ts
// MAL: lento cuando sobra, falla cuando falta
await page.waitForTimeout(5000);

// BIEN: espera lo justo
await expect(page.getByText('Guardado')).toBeVisible();
```

## Tests que no se vuelven flaky

Un test **flaky** pasa y falla sin que el código cambie. Casi siempre es por tiempos, datos compartidos entre tests o servicios externos. Combátelos así:

- Cada test crea y limpia sus propios datos; no dependas del orden.
- Nada de pausas fijas.
- Falsea los servicios externos.
- Si un test sigue siendo inestable, arréglalo o bórralo. Una suite en la que «a veces falla» deja de inspirar confianza y la gente deja de mirarla.

## Errores comunes

- Probar la implementación (el nombre de un método) en lugar del comportamiento.
- Buscar por clases CSS o por posición.
- Cubrirlo todo con E2E: lentos, frágiles y difíciles de depurar.

## Practica

Haz el reto de este módulo: cubre la lista de tareas con un test de componente en Vitest y con un test E2E en Playwright del flujo completo de iniciar sesión, crear una tarea y verla en la lista.
