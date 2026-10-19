import { execFileSync } from 'node:child_process';
import { existsSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { database, phpEnv, root } from './support/env';

/**
 * Runs once before the tests: checks the frontend is built and gives the suite a fresh,
 * seeded database. The web server is already up by now, but it only touches the
 * database when a request arrives.
 */
export default function globalSetup(): void {
    if (!existsSync(resolve(root, 'public/build/manifest.json'))) {
        throw new Error(
            'There is no frontend build. Run `npm run build` first, or use `npm run e2e`.',
        );
    }

    if (existsSync(resolve(root, 'public/hot'))) {
        throw new Error(
            'public/hot exists, so the app would load its assets from the Vite dev server. ' +
                'Stop `npm run dev` and delete public/hot.',
        );
    }

    for (const suffix of ['', '-wal', '-shm']) {
        rmSync(`${database}${suffix}`, { force: true });
    }

    writeFileSync(database, '');

    try {
        execFileSync(
            'php',
            [
                'artisan',
                'migrate:fresh',
                '--seed',
                '--force',
                '--no-interaction',
            ],
            { cwd: root, env: phpEnv, stdio: 'pipe', encoding: 'utf8' },
        );
    } catch (error) {
        const output = error as { stdout?: string; stderr?: string };

        throw new Error(
            `Could not prepare the e2e database.\n${output.stdout ?? ''}\n${output.stderr ?? ''}`,
        );
    }
}
