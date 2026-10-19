import { createAccount, expect, guest, signIn, test } from './fixtures';

guest.describe('as a visitor', () => {
    guest('is sent to the login page from a private page', async ({ page }) => {
        await page.goto('/dashboard');

        await expect(page).toHaveURL(/\/login$/);
    });

    guest('cannot reach the stack tools or the projects', async ({ page }) => {
        for (const path of ['/stacks/wizard', '/projects', '/jobs']) {
            await page.goto(path);

            await expect(page).toHaveURL(/\/login$/);
        }
    });

    guest('cannot sign in with a wrong password', async ({ page }) => {
        const account = createAccount('Wrong password');

        await page.goto('/login');
        await page.getByLabel('Email address').fill(account.email);
        await page
            .getByLabel('Password', { exact: true })
            .fill('not-the-password');
        await page.getByTestId('login-button').click();

        await expect(page.getByText(/credentials do not match/i)).toBeVisible();
        await expect(page).toHaveURL(/\/login$/);
    });

    guest('can sign in and sign out', async ({ page }) => {
        await signIn(page, createAccount('Sign in and out'));

        await expect(
            page.getByRole('heading', { name: 'Ruta Laravel', level: 1 }),
        ).toBeVisible();

        await page.getByTestId('sidebar-menu-button').click();
        await page.getByTestId('logout-button').click();

        await expect(page).toHaveURL(/\/login$/);
    });
});

test('a signed-in user lands on the roadmap', async ({ page }) => {
    await expect(
        page.getByRole('heading', { name: 'Ruta Laravel', level: 1 }),
    ).toBeVisible();
    await expect(page.getByRole('link', { name: 'Proyectos' })).toBeVisible();
});
