import { expect, test } from './fixtures';

test('every module has lessons that render without errors', async ({
    page,
}) => {
    // About 80 pages are visited, one after another.
    test.setTimeout(240_000);

    const problems: string[] = [];

    page.on('pageerror', (error) => {
        problems.push(`${page.url()}: ${error.message}`);
    });
    page.on('console', (message) => {
        // A missing font or image is not what this test is about.
        if (
            message.type() === 'error' &&
            !/Failed to load resource/.test(message.text())
        ) {
            problems.push(`${page.url()}: ${message.text()}`);
        }
    });

    await page.goto('/learn');

    const hrefs = async (pattern: RegExp) =>
        page
            .locator('a[href^="/learn/"]')
            .evaluateAll(
                (links, source) => [
                    ...new Set(
                        links
                            .map((link) => link.getAttribute('href') ?? '')
                            .filter((href) => new RegExp(source).test(href)),
                    ),
                ],
                pattern.source,
            );

    const modules = await hrefs(/^\/learn\/[^/]+$/);

    expect(modules.length).toBeGreaterThanOrEqual(26);

    const lessons: string[] = [];

    for (const module of modules) {
        await page.goto(module);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.getByLabel('Mis notas')).toBeVisible();

        const own = await hrefs(new RegExp(`^${module}/[^/]+$`));

        expect(
            own.length,
            `${module} should have at least two lessons`,
        ).toBeGreaterThanOrEqual(2);

        lessons.push(...own);
    }

    for (const lesson of lessons) {
        await page.goto(lesson);

        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        expect(
            await page.locator('article h2').count(),
            `${lesson} has sections`,
        ).toBeGreaterThan(0);

        // Code blocks come out as code, not as stripped or escaped HTML.
        expect(
            await page.locator('article pre').count(),
            `${lesson} has code`,
        ).toBeGreaterThan(0);
        // A lesson may talk about <script> (the security ones do), but must never contain one.
        await expect(page.locator('article script')).toHaveCount(0);
    }

    expect(problems).toEqual([]);
});

test('a lesson can be marked as read and the module shows it', async ({
    page,
}) => {
    await page.goto('/learn/sql/paginar-y-recorrer-tablas-grandes');

    const read = page.getByRole('checkbox', {
        name: 'Marcar esta lección como leída',
    });
    await expect(read).not.toBeChecked();

    await read.click();
    await expect(read).toBeChecked();

    await page.goto('/learn/sql');
    await expect(page.getByText('Leída')).toBeVisible();
});
