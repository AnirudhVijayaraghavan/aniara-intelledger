import AxeBuilder from '@axe-core/playwright';
import { expect, test as base } from '@playwright/test';
import type { Page } from '@playwright/test';

export const test = base.extend({
    page: async ({ page, colorScheme }, runTest) => {
        const failures: string[] = [];
        page.on('pageerror', (error) => failures.push(error.message));
        await page.route('**/*', async (route) => {
            const url = new URL(route.request().url());
            if (
                url.origin !== 'http://127.0.0.1:4178' ||
                route.request().method() !== 'GET'
            ) {
                failures.push('Unexpected non-fixture network request.');
                await route.abort();
                return;
            }
            await route.continue();
        });
        await page.goto('/');
        await expect(page.getByRole('heading', { level: 1 })).toBeVisible();
        await expect(page.locator('html')).toHaveClass(
            colorScheme === 'dark' ? 'dark' : '',
        );
        await runTest(page);
        expect(
            failures,
            'Component errors or unexpected network requests',
        ).toEqual([]);
    },
});

export async function expectAccessible(
    page: Page,
    popup?:
        | '[role="listbox"]'
        | '[role="menu"]'
        | '[data-slot="navigation-menu-content"]',
): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
        await new Promise(requestAnimationFrame);
        await Promise.all(
            document
                .getAnimations()
                .filter(
                    (animation) =>
                        animation.effect?.getComputedTiming().iterations !==
                        Infinity,
                )
                .map((animation) => animation.finished.catch(() => {})),
        );
    });
    const builder = new AxeBuilder({ page }).withTags([
        'wcag2a',
        'wcag2aa',
        'wcag21a',
        'wcag21aa',
        'wcag22aa',
    ]);
    // Axe's modal heuristic does not recognise Radix listbox/menu focus traps.
    // Navigation content is separately scoped with user approval for Radix's focus proxy.
    // Scan the active popup, and test focus routing with real keyboard input.
    if (popup) {
        builder.include(popup);
    }
    const result = await builder.analyze();
    // Keep failure output useful without emitting HTML snippets or input values.
    expect(
        result.violations.map(({ id, impact, nodes }) => ({
            id,
            impact,
            targets: nodes.map(({ target }) => target),
            related: nodes.flatMap((node) =>
                [...node.any, ...node.all, ...node.none].flatMap((check) =>
                    (check.relatedNodes ?? []).map(({ target }) => target),
                ),
            ),
        })),
        'WCAG A/AA violations',
    ).toEqual([]);
    if (result.incomplete.length > 0) {
        test.info().annotations.push({
            type: 'axe-manual-review',
            description: result.incomplete.map(({ id }) => id).join(', '),
        });
    }
}
