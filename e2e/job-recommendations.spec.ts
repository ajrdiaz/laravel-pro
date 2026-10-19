import type { Page } from '@playwright/test';
import { expect, test } from './fixtures';

const posting =
    'Buscamos backend Laravel con experiencia en APIs REST, Sanctum, Eloquent, MySQL, Pest y GitHub Actions.';

async function analyse(
    page: Page,
    text: string,
    title?: string,
): Promise<void> {
    await page.goto('/jobs');

    if (title) {
        await page.getByLabel('Título (opcional)').fill(title);
    }

    await page.getByLabel('Texto de la oferta').fill(text);
    await page.getByRole('button', { name: 'Analizar oferta' }).click();
    await page.waitForURL(/\/jobs\/\d+$/);
}

const recommendations = (page: Page) =>
    page.locator('section', {
        has: page.getByRole('heading', { name: 'Recomendaciones' }),
    });

test.describe('job recommendations', () => {
    test('the report suggests what to do next about the posting', async ({
        page,
    }) => {
        await analyse(page, posting);

        await expect(
            page.getByRole('heading', { name: 'Recomendaciones' }),
        ).toBeVisible();
        await expect(recommendations(page).getByRole('listitem')).toHaveCount(
            5,
        );

        for (const title of [
            'Úsala como mapa, no como meta',
            'Tu siguiente lección',
            'Un proyecto para tu portafolio',
            'El tipo de aplicación que describe',
            'Analiza más ofertas del mismo puesto',
        ]) {
            await expect(
                recommendations(page).getByRole('heading', { name: title }),
            ).toBeVisible();
        }

        // The text is plain: backticks from a stack's summary come out as code.
        await expect(recommendations(page)).not.toContainText('`');
    });

    test('the project suggestion leads to the project', async ({ page }) => {
        await analyse(page, posting);

        await recommendations(page)
            .getByRole('link', { name: 'Ver el proyecto' })
            .click();

        await expect(page).toHaveURL(/\/projects\/api-de-tareas$/);
        await expect(
            page.getByRole('heading', {
                name: 'API REST de tareas con tokens',
                level: 1,
            }),
        ).toBeVisible();
    });

    test('the stack suggestion leads to the stack', async ({ page }) => {
        await analyse(page, posting);

        await recommendations(page)
            .getByRole('link', { name: 'Ver el stack' })
            .click();

        await expect(page).toHaveURL(/\/stacks\/rest-api$/);
    });

    test('the lesson suggestion moves on once the lesson has been read', async ({
        page,
    }) => {
        await analyse(page, posting);
        const analysisUrl = page.url();

        const lessonLink = () =>
            recommendations(page).getByRole('link', {
                name: 'Leer la lección',
            });

        const firstHref = await lessonLink().getAttribute('href');

        await lessonLink().click();
        await expect(page).toHaveURL(new RegExp(`${firstHref}$`));

        await page
            .getByRole('checkbox', { name: 'Marcar esta lección como leída' })
            .click();
        await expect(
            page.getByRole('checkbox', {
                name: 'Marcar esta lección como leída',
            }),
        ).toBeChecked();

        await page.goto(analysisUrl);

        // The same module has a second lesson, or the next module's first one.
        await expect(lessonLink()).not.toHaveAttribute('href', firstHref ?? '');
    });

    test('the suggestion to review appears after studying a module the posting asks for', async ({
        page,
    }) => {
        await analyse(page, posting);
        await expect(
            recommendations(page).getByRole('heading', {
                name: 'Repasa lo que ya llevas',
            }),
        ).toHaveCount(0);

        // Reading a lesson is progress in its module.
        await recommendations(page)
            .getByRole('link', { name: 'Leer la lección' })
            .click();
        await page
            .getByRole('checkbox', { name: 'Marcar esta lección como leída' })
            .click();
        await expect(
            page.getByRole('checkbox', {
                name: 'Marcar esta lección como leída',
            }),
        ).toBeChecked();

        await page.goBack();
        await page.reload();

        await expect(
            recommendations(page).getByRole('heading', {
                name: 'Repasa lo que ya llevas',
            }),
        ).toBeVisible();
        await recommendations(page)
            .getByRole('link', { name: 'Repasar las tarjetas' })
            .click();
        await expect(page).toHaveURL(/\/flashcards\?module=/);
    });

    test('stops asking for more postings once there are three', async ({
        page,
    }) => {
        const more = 'Analiza más ofertas del mismo puesto';

        await analyse(page, posting, 'Primera');
        await expect(
            recommendations(page).getByRole('heading', { name: more }),
        ).toBeVisible();

        await analyse(page, posting, 'Segunda');
        await expect(
            recommendations(page).getByRole('heading', { name: more }),
        ).toBeVisible();

        await analyse(page, posting, 'Tercera');
        await expect(
            recommendations(page).getByRole('heading', { name: more }),
        ).toHaveCount(0);
    });

    test('names the topics outside the route and where to start', async ({
        page,
    }) => {
        await analyse(
            page,
            'Experiencia con AWS, Kubernetes y Terraform en producción.',
        );

        const outside = recommendations(page)
            .getByRole('listitem')
            .filter({
                has: page.getByRole('heading', {
                    name: 'Lo que ningún módulo enseña todavía',
                }),
            });

        await expect(outside).toContainText('Empieza por AWS');
        await expect(outside).toContainText('documentación de AWS');
    });

    test('has no recommendations section when the posting has nothing recognisable and no advice', async ({
        page,
    }) => {
        await analyse(
            page,
            'Texto sin ninguna tecnología reconocible, pero suficientemente largo para guardarse.',
        );

        // Only the general advice about analysing more postings is left.
        await expect(recommendations(page).getByRole('listitem')).toHaveCount(
            1,
        );
        await expect(
            recommendations(page).getByRole('heading', {
                name: 'Analiza más ofertas del mismo puesto',
            }),
        ).toBeVisible();
    });

    test('the report with recommendations fits a phone screen', async ({
        page,
    }) => {
        await page.setViewportSize({ width: 390, height: 844 });
        await analyse(page, posting);

        await expect(
            page.getByRole('heading', { name: 'Recomendaciones' }),
        ).toBeVisible();

        const overflow = await page.evaluate(
            () =>
                document.documentElement.scrollWidth -
                document.documentElement.clientWidth,
        );

        expect(overflow, 'horizontal overflow in pixels').toBeLessThanOrEqual(
            0,
        );
    });

    test('the form page gives tips for getting more out of an analysis', async ({
        page,
    }) => {
        await page.goto('/jobs');

        const tips = page.locator('section', {
            has: page.getByRole('heading', { name: 'Cómo sacarle partido' }),
        });

        await expect(tips).toBeVisible();
        await expect(tips.getByRole('listitem')).toHaveCount(5);
        await expect(tips).toContainText('Pega la oferta completa');
    });
});
