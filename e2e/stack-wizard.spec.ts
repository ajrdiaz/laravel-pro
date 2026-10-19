import { expect, test } from './fixtures';

test.describe('stack wizard', () => {
    test('recommends a stack and explains why', async ({ page }) => {
        await page.goto('/stacks/wizard');

        const submit = page.getByRole('button', {
            name: 'Recomendar un stack',
        });
        await expect(submit).toBeDisabled();

        await page
            .getByRole('radio', { name: /Solo un sitio web propio/ })
            .check();
        await page
            .getByRole('radio', { name: /Yo, o el mismo equipo/ })
            .check();
        await expect(submit).toBeDisabled();

        await page.getByRole('radio', { name: /Muy interactiva/ }).check();
        await expect(submit).toBeEnabled();
        await submit.click();

        await expect(
            page.getByRole('heading', { name: 'Tu recomendación' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /App web interactiva con Inertia/ }),
        ).toBeVisible();
        await expect(page.getByText('Por qué:').first()).toBeVisible();

        // The answers travel in the URL, so the result can be shared.
        await expect(page).toHaveURL(/clients=web/);
        await expect(page).toHaveURL(/team=solo/);
        await expect(page).toHaveURL(/ui=rich/);
    });

    test('scrolls to the result instead of leaving it below the form', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 1280, height: 720 });
        await page.goto('/stacks/wizard');

        await page
            .getByRole('radio', { name: /Solo un sitio web propio/ })
            .check();
        await page
            .getByRole('radio', { name: /Yo, o el mismo equipo/ })
            .check();
        await page.getByRole('radio', { name: /Sobre todo contenido/ }).check();
        await page.getByRole('button', { name: 'Recomendar un stack' }).click();

        await expect(
            page.getByRole('heading', { name: 'Tu recomendación' }),
        ).toBeInViewport();
    });

    test('a shared link shows the result straight away', async ({ page }) => {
        await page.goto('/stacks/wizard?clients=both&team=separate&ui=content');

        await expect(
            page.getByRole('heading', { name: 'Tu recomendación' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /REST API para móvil y terceros/ }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: /Frontend desacoplado/ }),
        ).toBeVisible();

        // The form comes back filled in.
        await expect(
            page.getByRole('radio', {
                name: /Un sitio web y también app móvil/,
            }),
        ).toBeChecked();
        await expect(
            page.getByRole('radio', { name: /Un equipo distinto/ }),
        ).toBeChecked();
    });

    test('adds the pieces that were ticked, and the queue they depend on', async ({
        page,
    }) => {
        await page.goto('/stacks/wizard?clients=web&team=solo&ui=rich');

        await page
            .getByRole('checkbox', {
                name: /Búsqueda con tolerancia a errores/,
            })
            .check();
        await page.getByRole('button', { name: 'Recomendar un stack' }).click();

        await expect(
            page.getByRole('link', { name: /Búsqueda con Scout/ }),
        ).toBeVisible();
        // Nobody asked for background work, but search needs a queue worker underneath.
        await expect(
            page.getByRole('link', { name: /Colas, workers y tareas/ }),
        ).toBeVisible();
    });

    test('shows how prepared the user is and which modules to start with', async ({
        page,
    }) => {
        await page.goto('/stacks/wizard?clients=web&team=solo&ui=rich');

        await expect(page.getByText('Qué tan preparado estás')).toBeVisible();
        await expect(page.getByText('0%').first()).toBeVisible();

        const startWith = page.getByRole('heading', {
            name: 'Empieza por estos módulos',
        });
        await expect(startWith).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'PHP moderno' }),
        ).toBeVisible();
    });

    test('starting again clears the answers and the result', async ({
        page,
    }) => {
        await page.goto('/stacks/wizard?clients=web&team=solo&ui=rich');
        await expect(
            page.getByRole('heading', { name: 'Tu recomendación' }),
        ).toBeVisible();

        await page.getByRole('button', { name: 'Empezar de nuevo' }).click();

        await expect(page).toHaveURL(/\/stacks\/wizard$/);
        await expect(
            page.getByRole('heading', { name: 'Tu recomendación' }),
        ).toHaveCount(0);
        await expect(page.getByRole('radio', { checked: true })).toHaveCount(0);
        await expect(
            page.getByRole('button', { name: 'Recomendar un stack' }),
        ).toBeDisabled();
    });

    test('can carry the recommended stacks over to the comparison', async ({
        page,
    }) => {
        // A web and mobile app gets two stacks (the API and the web one), so there is
        // something to compare. With a single recommendation the link is not offered.
        await page.goto('/stacks/wizard?clients=both&team=solo&ui=rich');

        await page.getByRole('link', { name: 'Comparar estos stacks' }).click();

        await expect(page).toHaveURL(/\/stacks\/compare\?/);
        await expect(page.getByLabel('Stack 1')).toHaveValue('rest-api');
        await expect(page.getByLabel('Stack 2')).toHaveValue(
            'inertia-vue-react',
        );
        await expect(page.getByRole('table')).toBeVisible();
    });

    test('offers no comparison when only one stack is recommended', async ({
        page,
    }) => {
        await page.goto('/stacks/wizard?clients=web&team=solo&ui=rich');

        await expect(
            page.getByRole('heading', { name: 'Tu recomendación' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Comparar estos stacks' }),
        ).toHaveCount(0);
    });

    test('is reachable from the stacks page', async ({ page }) => {
        await page.goto('/stacks');
        await page.getByRole('link', { name: '¿Qué stack uso?' }).click();

        await expect(page).toHaveURL(/\/stacks\/wizard$/);
        await expect(
            page.getByRole('heading', { name: '¿Qué stack uso?', level: 1 }),
        ).toBeVisible();
    });
});
