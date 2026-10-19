import type { Page } from '@playwright/test';
import { readFileSync } from 'node:fs';
import { expect, test } from './fixtures';

/** The number in the "Tarjetas" badge of the sidebar. */
async function dueInSidebar(page: Page): Promise<number> {
    const item = page
        .locator('[data-sidebar="menu-item"]')
        .filter({ hasText: 'Tarjetas' });

    return Number((await item.innerText()).match(/\d+/)?.[0] ?? 0);
}

async function pendingInQueue(page: Page): Promise<number> {
    const text = await page.getByText(/\d+ pendientes/).innerText();

    return Number(text.match(/\d+/)?.[0]);
}

test.describe('flashcards', () => {
    test('a card the user knew leaves the queue and the sidebar counter drops', async ({
        page,
    }) => {
        await page.goto('/flashcards');

        const dueBefore = await dueInSidebar(page);
        const queueBefore = await pendingInQueue(page);

        expect(dueBefore).toBeGreaterThan(0);

        await page.getByRole('button', { name: 'Mostrar respuesta' }).click();
        await page
            .getByRole('button', { name: 'Lo sabía', exact: true })
            .click();

        await expect.poll(() => pendingInQueue(page)).toBe(queueBefore - 1);
        // Only the stats are reloaded after an answer, and the badge follows them.
        await expect.poll(() => dueInSidebar(page)).toBe(dueBefore - 1);
    });

    test('a card the user missed comes round again in the same session', async ({
        page,
    }) => {
        await page.goto('/flashcards');
        const queueBefore = await pendingInQueue(page);
        const question = await page.locator('p.text-xl').innerText();

        await page.getByRole('button', { name: 'Mostrar respuesta' }).click();
        await page.getByRole('button', { name: 'No lo sabía' }).click();

        // It goes to the back of the queue, so nothing is left behind...
        await expect.poll(() => pendingInQueue(page)).toBe(queueBefore);
        // ...and a different card is next.
        await expect(page.locator('p.text-xl')).not.toHaveText(question);
    });

    test('hides the answer until it is asked for', async ({ page }) => {
        await page.goto('/flashcards');

        await expect(
            page.getByRole('button', { name: 'Lo sabía', exact: true }),
        ).toHaveCount(0);
        await expect(page.getByText('Responde en voz alta')).toBeVisible();

        await page.getByRole('button', { name: 'Mostrar respuesta' }).click();

        await expect(
            page.getByRole('button', { name: 'Lo sabía', exact: true }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'No lo sabía' }),
        ).toBeVisible();
    });
});

test.describe('Anki export', () => {
    test('downloads every card in a file Anki can import', async ({ page }) => {
        await page.goto('/flashcards');

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.getByRole('link', { name: 'Exportar a Anki' }).click(),
        ]);

        expect(download.suggestedFilename()).toBe('ruta-laravel-tarjetas.txt');

        const content = readFileSync(await download.path(), 'utf8');
        const lines = content.trimEnd().split('\n');

        expect(lines.slice(0, 4)).toEqual([
            '#separator:tab',
            '#html:true',
            '#guid column:1',
            '#tags column:4',
        ]);

        const rows = lines.slice(4);

        expect(rows.length).toBeGreaterThan(100);

        for (const row of rows) {
            expect(row.split('\t')).toHaveLength(4);
        }
    });

    test('can be limited to one module', async ({ page }) => {
        await page.goto('/flashcards');

        await page.getByRole('combobox', { name: 'Módulo' }).click();
        await page.getByRole('option', { name: 'PHP moderno' }).click();

        await expect(page).toHaveURL(/module=php/);

        const [download] = await Promise.all([
            page.waitForEvent('download'),
            page.getByRole('link', { name: 'Exportar a Anki' }).click(),
        ]);

        expect(download.suggestedFilename()).toBe('ruta-laravel-php.txt');

        const rows = readFileSync(await download.path(), 'utf8')
            .trimEnd()
            .split('\n')
            .slice(4);

        expect(rows.length).toBeGreaterThan(0);
        expect(rows.every((row) => row.endsWith('\truta-laravel::php'))).toBe(
            true,
        );
    });
});
