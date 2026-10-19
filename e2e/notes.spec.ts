import { expect, test } from './fixtures';

const noteEditor = (page: import('@playwright/test').Page) =>
    page.locator('section', { has: page.getByLabel('Mis notas') });

test.describe('notes on a module', () => {
    test('are saved while typing and are still there after a reload', async ({
        page,
    }) => {
        await page.goto('/learn/php');

        await page
            .getByLabel('Mis notas')
            .fill('Un enum respaldado se crea con from().');

        await expect(noteEditor(page).getByRole('status')).toHaveText(
            'Guardado',
        );

        await page.reload();

        await expect(page.getByLabel('Mis notas')).toHaveValue(
            'Un enum respaldado se crea con from().',
        );
    });

    test('are removed when the text is emptied', async ({ page }) => {
        await page.goto('/learn/php');
        const note = page.getByLabel('Mis notas');

        await note.fill('Esto se va a borrar');
        await expect(noteEditor(page).getByRole('status')).toHaveText(
            'Guardado',
        );

        await note.fill('');
        await expect(noteEditor(page).getByRole('status')).toHaveText(
            'Guardado',
        );

        await page.reload();

        await expect(page.getByLabel('Mis notas')).toHaveValue('');
    });

    test('are not lost when the user leaves right after typing', async ({
        page,
    }) => {
        await page.goto('/learn/php');

        const saved = page.waitForResponse(
            (response) =>
                response.url().endsWith('/learn/php/notes') &&
                response.request().method() === 'PUT',
        );

        // Fewer than the 800 ms the editor waits before saving on its own.
        await page.getByLabel('Mis notas').fill('Última idea antes de salir');
        await page.getByRole('link', { name: 'Todos los módulos' }).click();

        expect((await saved).ok()).toBe(true);

        await page.goto('/learn/php');

        await expect(page.getByLabel('Mis notas')).toHaveValue(
            'Última idea antes de salir',
        );
    });

    test('are shown on the lessons of the same module, and only there', async ({
        page,
    }) => {
        await page.goto('/learn/php');
        await page.getByLabel('Mis notas').fill('Nota del módulo de PHP');
        await expect(noteEditor(page).getByRole('status')).toHaveText(
            'Guardado',
        );

        await page.goto('/learn/php/flechas-callables-y-colecciones');
        await expect(page.getByLabel('Mis notas')).toHaveValue(
            'Nota del módulo de PHP',
        );

        await page.goto('/learn/sql/paginar-y-recorrer-tablas-grandes');
        await expect(page.getByLabel('Mis notas')).toHaveValue('');
    });

    test('keep their line breaks and show markup as plain text', async ({
        page,
    }) => {
        const text = 'Primera línea\n<b>no es negrita</b>\n    sangrada';

        await page.goto('/learn/php');
        await page.getByLabel('Mis notas').fill(text);
        await expect(noteEditor(page).getByRole('status')).toHaveText(
            'Guardado',
        );

        await page.reload();

        await expect(page.getByLabel('Mis notas')).toHaveValue(text);
        await expect(
            page.locator('b', { hasText: 'no es negrita' }),
        ).toHaveCount(0);
    });

    test('warn when the note is too long and keep the saved one', async ({
        page,
    }) => {
        await page.goto('/learn/php');
        const note = page.getByLabel('Mis notas');

        await note.fill('Nota corta que sí se guarda');
        await expect(noteEditor(page).getByRole('status')).toHaveText(
            'Guardado',
        );

        // The browser stops typing and pasting at the textarea's maxlength, so the server's
        // own limit (the page must cope with its answer) is reached with that limit removed.
        await note.evaluate((element: HTMLTextAreaElement) => {
            element.removeAttribute('maxlength');
        });
        await note.fill('x'.repeat(20_001));

        await expect(noteEditor(page).getByRole('status')).toContainText(
            'demasiado larga',
        );

        await page.reload();

        await expect(page.getByLabel('Mis notas')).toHaveValue(
            'Nota corta que sí se guarda',
        );
    });
});
