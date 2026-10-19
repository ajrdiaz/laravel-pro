import { dirname, resolve } from 'node:path';
import { fileURLToPath } from 'node:url';

export const root = resolve(dirname(fileURLToPath(import.meta.url)), '../..');

export const port = Number(process.env.E2E_PORT ?? 8010);
export const baseURL = `http://127.0.0.1:${port}`;

/** The database the browser tests use. It is rebuilt from scratch on every run. */
export const database = resolve(root, 'database/e2e.sqlite');

const inherited = Object.fromEntries(
    Object.entries(process.env).filter(
        (entry): entry is [string, string] => entry[1] !== undefined,
    ),
);

/**
 * Environment for every PHP process of the suite (the web server, the migrations and the
 * helper that creates users). Variables already in the environment win over `.env`, so
 * this isolates the tests from whatever the local `.env` says.
 */
export const phpEnv: Record<string, string> = {
    ...inherited,
    APP_ENV: 'local',
    APP_DEBUG: 'true',
    APP_URL: baseURL,
    // A throwaway key: this database holds nothing but test data.
    APP_KEY: `base64:${Buffer.alloc(32, 'ruta-laravel-e2e').toString('base64')}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: database,
    DB_URL: '',
    // No lockouts between tests, no mail, no queue, no writes to the dev log
    // (E2E_SERVER_LOG=1 sends the application's log and the server's to the terminal).
    CACHE_STORE: 'array',
    QUEUE_CONNECTION: 'sync',
    MAIL_MAILER: 'log',
    BROADCAST_CONNECTION: 'log',
    LOG_CHANNEL: process.env.E2E_SERVER_LOG ? 'stderr' : 'null',
    SESSION_DRIVER: 'database',
    SESSION_DOMAIN: 'null',
    SESSION_SECURE_COOKIE: 'false',
};
