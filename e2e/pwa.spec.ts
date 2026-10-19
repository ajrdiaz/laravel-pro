import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

// The one file where the service worker runs: it is what is being tested.
test.use({ serviceWorkers: 'allow' });

const PAGES_CACHE = 'ruta-pages-v1';
const ASSETS_CACHE = 'ruta-assets-v1';

const lesson = '/learn/php/flechas-callables-y-colecciones';
const otherLesson = '/learn/sql/paginar-y-recorrer-tablas-grandes';

/**
 * Loads a page and waits until the service worker controls it, so what is opened next
 * goes through the worker. The first visit installs it, the second one is controlled.
 */
async function controlledBy(page: Page, path: string): Promise<void> {
    await page.goto(path);

    await page.waitForFunction(
        () => navigator.serviceWorker.controller !== null,
        undefined,
        { timeout: 15_000 },
    );
}

async function savedPages(page: Page, cache = PAGES_CACHE): Promise<string[]> {
    return page.evaluate(async (name) => {
        if (!(await caches.has(name))) {
            return [];
        }

        const requests = await (await caches.open(name)).keys();

        return requests.map((request) => new URL(request.url).pathname);
    }, cache);
}

test.describe('installable app', () => {
    test('links a manifest with the icons it declares', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page.locator('link[rel="manifest"]')).toHaveAttribute(
            'href',
            '/manifest.json',
        );

        const response = await page.request.get('/manifest.json');
        expect(response.ok()).toBe(true);

        const manifest = await response.json();

        expect(manifest.display).toBe('standalone');
        expect(manifest.start_url).toBe('/dashboard');

        for (const icon of manifest.icons) {
            const file = await page.request.get(icon.src);

            expect(file.status(), icon.src).toBe(200);
            expect(file.headers()['content-type'], icon.src).toContain(
                'image/png',
            );
        }
    });

    test('registers a service worker that takes control of the app', async ({
        page,
    }) => {
        await controlledBy(page, '/dashboard');

        const worker = await page.evaluate(async () => {
            const registration = await navigator.serviceWorker.ready;

            return {
                state: registration.active?.state,
                script: registration.active?.scriptURL,
                scope: registration.scope,
            };
        });

        expect(worker.state).toBe('activated');
        expect(worker.script).toMatch(/\/sw\.js$/);
        expect(worker.scope).toMatch(/\/$/);
    });

    test('keeps the built files, which never change, for later', async ({
        page,
    }) => {
        await controlledBy(page, '/dashboard');

        // The files of the first visit loaded before the worker took control.
        await page.reload();

        await expect
            .poll(() =>
                savedPages(page, ASSETS_CACHE).then((paths) => paths.length),
            )
            .toBeGreaterThan(0);

        expect(
            (await savedPages(page, ASSETS_CACHE)).every((path) =>
                path.startsWith('/build/assets/'),
            ),
        ).toBe(true);
    });

    test('offers to install when the browser says the app can be installed', async ({
        page,
    }) => {
        await page.goto('/dashboard');

        await expect(page.getByTestId('install-app')).toHaveCount(0);

        await page.evaluate(() => {
            const event = new Event('beforeinstallprompt', {
                cancelable: true,
            });

            Object.assign(event, {
                prompt: async () => {
                    (window as unknown as { prompted: boolean }).prompted =
                        true;
                },
                userChoice: Promise.resolve({ outcome: 'accepted' }),
            });

            window.dispatchEvent(event);
        });

        const button = page.getByTestId('install-app');
        await expect(button).toBeVisible();

        await button.click();

        expect(
            await page.evaluate(
                () => (window as unknown as { prompted?: boolean }).prompted,
            ),
        ).toBe(true);
        // The browser lets a prompt be used once, so the button goes away.
        await expect(button).toHaveCount(0);
    });
});

test.describe('offline reading', () => {
    test('shows a notice while there is no connection and hides it when it returns', async ({
        page,
        context,
    }) => {
        await page.goto('/dashboard');

        const notice = page.getByText(
            /Sin conexión\. Puedes leer las lecciones/,
        );
        await expect(notice).toHaveCount(0);

        await context.setOffline(true);
        await expect(notice).toBeVisible();

        await context.setOffline(false);
        await expect(notice).toHaveCount(0);
    });

    test('a lesson opened online can be read again without a connection', async ({
        page,
        context,
    }) => {
        await controlledBy(page, '/learn');
        await page.goto(lesson);
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();

        const title = await page.getByRole('heading', { level: 1 }).innerText();

        await expect.poll(() => savedPages(page)).toContain(lesson);

        await context.setOffline(true);

        // The offline mode has to reach the worker too, or this test would prove nothing:
        // a lesson that was never opened cannot come from anywhere.
        const unopenedIsReachable = await page.evaluate(
            (path) =>
                fetch(path).then(
                    () => true,
                    () => false,
                ),
            otherLesson,
        );
        expect(unopenedIsReachable).toBe(false);

        await page.reload();

        await expect(page.getByRole('heading', { level: 1 })).toHaveText(title);
        await expect(
            page.getByText(/Sin conexión\. Puedes leer las lecciones/),
        ).toBeVisible();
    });

    test('a page that was never opened shows the offline page', async ({
        page,
        context,
    }) => {
        await controlledBy(page, '/dashboard');

        await context.setOffline(true);
        await page.goto('/stacks');

        await expect(
            page.getByRole('heading', { name: 'Sin conexión', level: 1 }),
        ).toBeVisible();
        await expect(
            page.getByRole('button', { name: 'Reintentar' }),
        ).toBeVisible();
        await expect(
            page.getByRole('link', { name: 'Ir a las lecciones' }),
        ).toHaveAttribute('href', '/learn');
    });

    test('moving between lessons inside the app works without a connection', async ({
        page,
        context,
    }) => {
        await controlledBy(page, '/learn');

        // Go through the app, so the pages are saved the way the app asks for them.
        await page
            .getByRole('link', { name: /PHP moderno/ })
            .first()
            .click();
        await expect(page).toHaveURL(/\/learn\/php$/);

        await page.getByRole('link', { name: /Funciones de flecha/ }).click();
        await expect(page).toHaveURL(new RegExp(`${lesson}$`));

        await page.getByRole('link', { name: 'PHP moderno' }).first().click();
        await expect(page).toHaveURL(/\/learn\/php$/);

        await expect.poll(() => savedPages(page)).toContain('/learn/php');

        await context.setOffline(true);

        await page.getByRole('link', { name: /Funciones de flecha/ }).click();

        await expect(page).toHaveURL(new RegExp(`${lesson}$`));
        await expect(
            page.getByRole('heading', {
                name: /Funciones de flecha/,
                level: 1,
            }),
        ).toBeVisible();
    });

    test('a page that was never opened explains why it did not load', async ({
        page,
        context,
    }) => {
        await controlledBy(page, '/learn');
        await page
            .getByRole('link', { name: /PHP moderno/ })
            .first()
            .click();
        await expect(page).toHaveURL(/\/learn\/php$/);

        await context.setOffline(true);

        // A lesson of the same module that was never opened: no copy, no connection.
        await page
            .getByRole('link', { name: /El PHP moderno que verás/ })
            .click();

        await expect(
            page.getByText(
                /No se pudo conectar\. Sin conexión solo puedes abrir/,
            ),
        ).toBeVisible();
        await expect(page).toHaveURL(/\/learn\/php$/);
    });
});

test.describe('saved lessons belong to one user', () => {
    test('signing out deletes them', async ({ page }) => {
        await controlledBy(page, '/learn');
        await page.goto(lesson);
        await expect.poll(() => savedPages(page)).toContain(lesson);

        await page.getByTestId('sidebar-menu-button').click();
        await page.getByTestId('logout-button').click();
        await expect(page).toHaveURL(/\/login$/);

        await expect.poll(() => savedPages(page)).toEqual([]);
    });

    test('a different user signing in does not inherit them', async ({
        page,
    }) => {
        await controlledBy(page, '/learn');
        await page.goto(lesson);
        await expect.poll(() => savedPages(page)).toContain(lesson);

        // As if another account had been the last one on this device.
        await page.evaluate(() =>
            localStorage.setItem('ruta-offline-user', '999999'),
        );
        await page.goto('/dashboard');

        await expect.poll(() => savedPages(page)).not.toContain(lesson);
    });

    test('the same user keeps them between visits', async ({ page }) => {
        await controlledBy(page, '/learn');
        await page.goto(lesson);
        await expect.poll(() => savedPages(page)).toContain(lesson);

        await page.goto('/dashboard');
        await page.goto('/learn');

        expect(await savedPages(page)).toContain(lesson);
    });
});
