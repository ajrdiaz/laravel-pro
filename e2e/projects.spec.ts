import { expect, test } from './fixtures';

const project = '/projects/api-de-tareas';

test.describe('portfolio projects', () => {
    test('are listed with their level, estimate and progress', async ({
        page,
    }) => {
        await page.goto('/projects');

        await expect(
            page.getByRole('heading', { name: 'Proyectos', level: 1 }),
        ).toBeVisible();

        for (const title of [
            'API REST de tareas con tokens',
            'Tablero de candidaturas con Inertia y Vue',
            'Importador de CSV con colas y reporte',
            'Chat de equipo en tiempo real',
        ]) {
            await expect(
                page.getByRole('link', { name: new RegExp(title) }),
            ).toBeVisible();
        }

        const api = page.getByRole('link', { name: /API REST de tareas/ });
        await expect(api).toContainText('Básico');
        await expect(api).toContainText('0/8 hitos');
        await expect(
            page.getByRole('link', { name: /Chat de equipo/ }),
        ).toContainText('Avanzado');
    });

    test('a milestone can be ticked and the progress follows', async ({
        page,
    }) => {
        await page.goto(project);

        const milestone = page.getByRole('checkbox', {
            name: 'Modelo y migraciones',
        });
        await expect(milestone).not.toBeChecked();
        await expect(page.getByText('0/8', { exact: true })).toBeVisible();

        // The box changes once the server has answered, so click and then wait for it.
        await milestone.click();

        await expect(milestone).toBeChecked();
        await expect(page.getByText('1/8', { exact: true })).toBeVisible();

        // It is saved: it is still ticked after a reload, and the list shows it.
        await page.reload();
        await expect(
            page.getByRole('checkbox', { name: 'Modelo y migraciones' }),
        ).toBeChecked();

        await page.goto('/projects');
        await expect(
            page.getByRole('link', { name: /API REST de tareas/ }),
        ).toContainText('1/8 hitos');
    });

    test('a milestone can be unticked again', async ({ page }) => {
        await page.goto(project);
        const milestone = page.getByRole('checkbox', {
            name: 'Modelo y migraciones',
        });

        await milestone.click();
        await expect(milestone).toBeChecked();
        await expect(page.getByText('1/8', { exact: true })).toBeVisible();

        await milestone.click();

        await expect(milestone).not.toBeChecked();
        await expect(page.getByText('0/8', { exact: true })).toBeVisible();
    });

    test('count as studying today but do not move the roadmap progress', async ({
        page,
    }) => {
        const tile = (label: string) =>
            page.locator('dl > div').filter({ hasText: label }).locator('dd');

        await page.goto('/dashboard');
        await expect(tile('Progreso total')).toHaveText('0%');
        await expect(tile('Días seguidos')).toHaveText('0');

        await page.goto(project);
        await page
            .getByRole('checkbox', { name: 'Modelo y migraciones' })
            .click();
        await expect(page.getByText('1/8', { exact: true })).toBeVisible();

        await page.goto('/dashboard');

        // Projects are practice on top of the roadmap, not part of it...
        await expect(tile('Progreso total')).toHaveText('0%');
        // ...but working on one is studying.
        await expect(tile('Días seguidos')).toHaveText('1');
    });

    test('shows commands and identifiers as code, and links to the stack and modules', async ({
        page,
    }) => {
        await page.goto(project);

        await expect(page.locator('ol code').first()).toBeVisible();
        await expect(
            page.getByRole('link', { name: /REST API para móvil y terceros/ }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Módulos que practicas' }),
        ).toBeVisible();
        await expect(
            page.getByRole('heading', { name: 'Qué debes tener al final' }),
        ).toBeVisible();
    });

    test('is reachable from the sidebar', async ({ page }) => {
        await page.getByRole('link', { name: 'Proyectos' }).click();

        await expect(page).toHaveURL(/\/projects$/);
    });
});
