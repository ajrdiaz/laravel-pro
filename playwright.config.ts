import { chromium, defineConfig } from '@playwright/test';
import { existsSync } from 'node:fs';
import { baseURL, phpEnv, port } from './e2e/support/env';

// Playwright's own Chromium is the default. When it is not installed, fall back to the
// Chrome on this machine, so the suite runs without a download. PW_CHANNEL forces one
// (`chrome`, `msedge`).
const channel =
    process.env.PW_CHANNEL ??
    (existsSync(chromium.executablePath()) ? undefined : 'chrome');

export default defineConfig({
    testDir: './e2e',
    globalSetup: './e2e/global-setup.ts',

    // One worker: the PHP built-in server handles a single request at a time anyway,
    // and a small suite is easier to debug when it runs in order.
    fullyParallel: false,
    workers: 1,
    forbidOnly: Boolean(process.env.CI),
    retries: process.env.CI ? 1 : 0,
    reporter: [['list'], ['html', { open: 'never' }]],

    use: {
        baseURL,
        channel,
        locale: 'es-ES',
        // The app's service worker keeps pages for offline reading. It would only get in the
        // way of tests about something else, so e2e/pwa.spec.ts is the one place it is allowed.
        serviceWorkers: 'block',
        // The app marks elements with `data-test`, the starter kit's convention.
        testIdAttribute: 'data-test',
        trace: 'retain-on-failure',
        screenshot: 'only-on-failure',
    },

    // The app runs against its own SQLite file (see e2e/support/env.ts), never against
    // the development database. A server already listening on the port is not reused,
    // because it would not be the one pointing at that file.
    webServer: {
        // Like `artisan serve`: from public/, with the framework's router script.
        command: `php -S 127.0.0.1:${port} ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`,
        cwd: 'public',
        url: `${baseURL}/up`,
        env: phpEnv,
        reuseExistingServer: false,
        timeout: 60_000,
        stdout: 'ignore',
        // The PHP server logs every request to stderr: only show it when asked.
        stderr: process.env.E2E_SERVER_LOG ? 'pipe' : 'ignore',
    },
});
