import { expect, test } from './fixtures';

// A phone. The pages must fit in it: nothing may push the page wider than the screen.
test.use({ viewport: { width: 390, height: 844 } });

const pages = [
    '/dashboard',
    '/learn',
    '/learn/php',
    '/learn/php/flechas-callables-y-colecciones',
    '/flashcards',
    '/quiz',
    '/challenges',
    '/interview',
    '/stacks',
    '/stacks/rest-api',
    '/stacks/wizard?clients=both&team=separate&ui=content&extras[]=search',
    '/stacks/compare?stacks[]=rest-api&stacks[]=tiempo-real&stacks[]=colas-y-workers',
    '/projects',
    '/projects/chat-de-equipo',
    '/jobs',
];

for (const path of pages) {
    test(`${path} fits a phone screen`, async ({ page }) => {
        await page.goto(path);
        await expect(page.getByRole('main')).toBeVisible();

        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );

        expect(overflow, 'horizontal overflow in pixels').toBeLessThanOrEqual(
            0,
        );
    });
}

test('the comparison table scrolls inside its own box', async ({ page }) => {
    await page.goto('/stacks/compare?stacks[]=rest-api&stacks[]=tiempo-real');

    const box = page.locator('.overflow-x-auto', {
        has: page.getByRole('table'),
    });

    const scrolls = await box.evaluate(
        (element) => element.scrollWidth > element.clientWidth,
    );

    expect(scrolls).toBe(true);
});
