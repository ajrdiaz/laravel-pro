import { expect, test } from './fixtures';

const posting =
    'Buscamos desarrollador Laravel con experiencia en PHP 8, Eloquent, APIs REST, Docker, ' +
    'Redis y colas. Se valora Vue.js, Inertia, Pest para testing, GitHub Actions y ' +
    'conocimientos de Kubernetes y AWS. Trabajo con MySQL y metodología Scrum.';

test.describe('job posting analyzer', () => {
    test('turns a posting into what to study and what is outside the route', async ({
        page,
    }) => {
        await page.goto('/jobs');

        await page
            .getByLabel('Título (opcional)')
            .fill('Backend Laravel Semi Senior');
        await page.getByLabel('Texto de la oferta').fill(posting);
        await page.getByRole('button', { name: 'Analizar oferta' }).click();

        await expect(page).toHaveURL(/\/jobs\/\d+$/);
        await expect(
            page.getByRole('heading', {
                name: 'Backend Laravel Semi Senior',
                level: 1,
            }),
        ).toBeVisible();

        // The three tiles.
        await expect(page.getByText('Habilidades detectadas')).toBeVisible();
        await expect(page.getByText('Cubiertas por tu ruta')).toBeVisible();
        await expect(page.getByText('Tu preparación')).toBeVisible();

        // Roadmap modules the posting asks for, with the user's progress in each.
        await expect(
            page.getByRole('heading', { name: 'Qué estudiar primero' }),
        ).toBeVisible();
        await expect(page.getByText('Testing con Pest')).toBeVisible();
        await expect(page.getByText('Docker y despliegue')).toBeVisible();

        // What no module teaches yet, with a hint.
        await expect(
            page.getByRole('heading', { name: 'Fuera de tu ruta' }),
        ).toBeVisible();
        await expect(
            page.getByText('Kubernetes', { exact: true }),
        ).toBeVisible();
        await expect(page.getByText('AWS', { exact: true })).toBeVisible();
    });

    test('keeps a history, and an analysis can be deleted', async ({
        page,
    }) => {
        await page.goto('/jobs');
        await page.getByLabel('Título (opcional)').fill('Oferta para borrar');
        await page.getByLabel('Texto de la oferta').fill(posting);
        await page.getByRole('button', { name: 'Analizar oferta' }).click();
        await expect(page).toHaveURL(/\/jobs\/\d+$/);

        await page.goto('/jobs');
        await expect(
            page.getByRole('heading', { name: 'Tus análisis' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /Oferta para borrar/ }),
        ).toBeVisible();

        await page.getByRole('link', { name: /Oferta para borrar/ }).click();
        await page
            .getByRole('button', { name: 'Borrar este análisis' })
            .click();
        await page.getByRole('button', { name: 'Sí, borrar' }).click();

        await expect(page).toHaveURL(/\/jobs$/);
        await expect(
            page.getByRole('heading', { name: 'Tus análisis' }),
        ).toHaveCount(0);
    });

    test('asks for the whole posting when the text is too short', async ({
        page,
    }) => {
        await page.goto('/jobs');

        await page.getByLabel('Texto de la oferta').fill('Laravel');
        await page.getByRole('button', { name: 'Analizar oferta' }).click();

        await expect(
            page.getByText(/demasiado corto para analizarlo/),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/jobs$/);
    });

    test('marks a module the user has not started as "Por empezar"', async ({
        page,
    }) => {
        await page.goto('/jobs');
        await page
            .getByLabel('Texto de la oferta')
            .fill(
                'Se necesita conocimiento sólido de PHP moderno, Eloquent y Docker para el puesto de backend.',
            );
        await page.getByRole('button', { name: 'Analizar oferta' }).click();
        await expect(page).toHaveURL(/\/jobs\/\d+$/);

        const phpCard = page.locator('li', { hasText: 'PHP moderno' }).first();
        await expect(phpCard).toContainText('Por empezar');
    });
});
