import { expect, test as base, type Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { resolve } from 'node:path';
import { phpEnv, root } from './support/env';

export type Account = { email: string; password: string };

const createUserScript = resolve(root, 'e2e/support/create-user.php');

/** A new verified user in the e2e database. */
export function createAccount(name: string): Account {
    const output = execFileSync('php', [createUserScript, name], {
        cwd: root,
        env: phpEnv,
        encoding: 'utf8',
    });

    // Take the last line, in case PHP printed a notice before the JSON.
    const line = output.trim().split('\n').pop() ?? '';

    return JSON.parse(line) as Account;
}

export async function signIn(page: Page, account: Account): Promise<void> {
    await page.goto('/login');
    await page.getByLabel('Email address').fill(account.email);
    await page.getByLabel('Password', { exact: true }).fill(account.password);
    await page.getByTestId('login-button').click();
    await page.waitForURL('**/dashboard');
}

/**
 * `test` gives every test its own signed-in user. Use `guest` for what a visitor sees.
 */
export const test = base.extend<{ account: Account }>({
    // Playwright reads the fixtures a function needs from its first parameter, which has
    // to be a destructuring pattern: an empty one says it needs none.
    // eslint-disable-next-line no-empty-pattern
    account: async ({}, use, testInfo) => {
        await use(createAccount(`E2E ${testInfo.title}`.slice(0, 60)));
    },

    page: async ({ page, account }, use) => {
        await signIn(page, account);
        await use(page);
    },
});

export const guest = base;

export { expect };
