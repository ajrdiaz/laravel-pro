import { expect, test } from './fixtures';

test.describe('stack comparison', () => {
    test('asks for two stacks before comparing', async ({ page }) => {
        await page.goto('/stacks/compare');

        await expect(page.getByText('Elige al menos dos stacks')).toBeVisible();
        await expect(page.getByRole('table')).toHaveCount(0);

        await page.getByLabel('Stack 1').selectOption('rest-api');

        // One is still not enough.
        await expect(page.getByRole('table')).toHaveCount(0);
    });

    test('lines two stacks up and then a third, keeping the choice in the URL', async ({
        page,
    }) => {
        await page.goto('/stacks/compare');

        await page.getByLabel('Stack 1').selectOption('rest-api');
        await page.getByLabel('Stack 2').selectOption('tiempo-real');

        const table = page.getByRole('table');
        await expect(table).toBeVisible();
        await expect(
            table.getByRole('columnheader', { name: /REST API/ }),
        ).toBeVisible();
        await expect(
            table.getByRole('columnheader', { name: /Tiempo real/ }),
        ).toBeVisible();
        await expect(table.getByRole('columnheader')).toHaveCount(3); // the label column + two stacks

        await page
            .getByLabel('Stack 3 (opcional)')
            .selectOption('colas-y-workers');

        await expect(table.getByRole('columnheader')).toHaveCount(4);
        await expect(page).toHaveURL(/stacks%5B2%5D=colas-y-workers/);
    });

    test('shows the layers, the reasons and the readiness of each stack', async ({
        page,
    }) => {
        await page.goto(
            '/stacks/compare?stacks[]=rest-api&stacks[]=tiempo-real',
        );

        const table = page.getByRole('table');

        for (const row of [
            'Backend',
            'Elígelo cuando',
            'Evítalo cuando',
            'Ventajas',
            'Costos',
            'Tu preparación',
        ]) {
            await expect(
                table.getByRole('rowheader', { name: row }),
            ).toBeVisible();
        }

        await expect(
            table
                .getByRole('row', { name: /Tu preparación/ })
                .getByText(/\d+%/)
                .first(),
        ).toBeVisible();
    });

    test('renders `code` in the cells instead of showing the backticks', async ({
        page,
    }) => {
        await page.goto(
            '/stacks/compare?stacks[]=tiempo-real&stacks[]=colas-y-workers',
        );

        const table = page.getByRole('table');

        await expect(table.locator('code').first()).toBeVisible();
        await expect(table).not.toContainText('`');
    });

    test('a shared link opens the comparison already filled in', async ({
        page,
    }) => {
        await page.goto(
            '/stacks/compare?stacks[]=monolito-blade&stacks[]=inertia-vue-react&stacks[]=rest-api',
        );

        await expect(page.getByLabel('Stack 1')).toHaveValue('monolito-blade');
        await expect(page.getByLabel('Stack 2')).toHaveValue(
            'inertia-vue-react',
        );
        await expect(page.getByLabel('Stack 3 (opcional)')).toHaveValue(
            'rest-api',
        );
        await expect(
            page.getByRole('table').getByRole('columnheader'),
        ).toHaveCount(4);
    });

    test('unknown stacks in the link are ignored', async ({ page }) => {
        await page.goto(
            '/stacks/compare?stacks[]=rest-api&stacks[]=nope&stacks[]=tiempo-real',
        );

        await expect(page.getByLabel('Stack 1')).toHaveValue('rest-api');
        await expect(page.getByLabel('Stack 2')).toHaveValue('tiempo-real');
        await expect(page.getByRole('table')).toBeVisible();
    });

    test('is reachable from the stacks page', async ({ page }) => {
        await page.goto('/stacks');
        await page.getByRole('link', { name: 'Comparar stacks' }).click();

        await expect(page).toHaveURL(/\/stacks\/compare$/);
    });
});
