import { expect } from '@playwright/test';
import { expectAccessible, test } from './accessibility';
import { expectFocusRing } from './focus';

test('checkbox names, mixed state, disabled tab order and dynamic error semantics', async ({
    page,
}) => {
    const choice = page.getByRole('checkbox', {
        name: 'Include synthetic example',
    });
    await page
        .getByRole('button', { name: 'Validate synthetic choice' })
        .focus();
    await page.keyboard.press('Enter');
    await expect(page.locator('#shared-error')).toHaveAttribute(
        'role',
        'alert',
    );
    await expect(page.locator('#shared-error')).toContainText(
        'Choose the synthetic example.',
    );
    await expect(choice).toHaveAttribute('aria-invalid', 'true');
    await expect(choice).toHaveAccessibleDescription(
        'This changes only local fixture state. Choose the synthetic example.',
    );
    await page.keyboard.press('Shift+Tab');
    await expect(choice).toBeFocused();
    await page.keyboard.press('Space');
    await expect(choice).toBeChecked();
    await expectFocusRing(choice);
    await expect(page.locator('#shared-error')).toBeHidden();
    await expect(choice).toHaveAccessibleDescription(
        'This changes only local fixture state.',
    );
    const mixed = page.getByRole('checkbox', {
        name: 'Partially selected examples',
    });
    await expect(mixed).toHaveAttribute('aria-checked', 'mixed');
    await mixed.focus();
    await page.keyboard.press('Space');
    await expect(mixed).toBeChecked();
    await expect(
        page.getByRole('checkbox', { name: 'Unavailable checkbox' }),
    ).toBeDisabled();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'After unavailable checkbox' }),
    ).toBeFocused();
    await expectAccessible(page);
});

test('toggle and single-select toggle group expose pressed or radio state and skip disabled items', async ({
    page,
}) => {
    const toggle = page.getByRole('button', {
        name: 'Pin example',
        exact: true,
    });
    await toggle.focus();
    await page.keyboard.press('Space');
    await expect(toggle).toHaveAttribute('aria-pressed', 'true');
    await expectFocusRing(toggle);
    await page.keyboard.press('Enter');
    await expect(toggle).toHaveAttribute('aria-pressed', 'false');
    await expect(
        page.getByRole('button', { name: 'Unavailable pin' }),
    ).toBeDisabled();
    const group = page.getByRole('radiogroup', { name: 'Synthetic view' });
    const usd = group.getByRole('radio', { name: 'USD view' });
    const inr = group.getByRole('radio', { name: 'INR view' });
    await usd.focus();
    await page.keyboard.press('ArrowRight');
    await expect(inr).toBeFocused();
    await expectFocusRing(inr);
    await page.keyboard.press('Space');
    await expect(inr).toBeChecked();
    await expect(usd).not.toBeChecked();
    await expect(
        group.getByRole('radio', { name: 'Unavailable view' }),
    ).toBeDisabled();
    await page.keyboard.press('Home');
    await expect(usd).toBeFocused();
    await expectAccessible(page);
});

test('tooltip is keyboard-described, Escape dismissible and hoverable at narrow widths', async ({
    page,
}) => {
    const trigger = page.getByRole('button', {
        name: 'Explain recorded activity',
    });
    await page.getByRole('radio', { name: 'USD view' }).focus();
    await trigger.scrollIntoViewIfNeeded();
    // Deliver pending scroll events before focus opens Radix's scroll-dismissed tooltip.
    await page.evaluate(
        () =>
            new Promise<void>((resolve) =>
                requestAnimationFrame(() =>
                    requestAnimationFrame(() => resolve()),
                ),
            ),
    );
    await page.keyboard.press('Tab');
    await expect(page.getByRole('tooltip')).toBeVisible();
    await expect(trigger).toHaveAccessibleDescription(/synthetic observations/);
    await expectFocusRing(trigger);
    await expectAccessible(page);
    await page.keyboard.press('Escape');
    await expect(page.getByRole('tooltip')).toBeHidden();
    await expect(trigger).toBeFocused();
    await page.setViewportSize({ width: 320, height: 900 });
    await trigger.hover();
    const popup = page.locator('[data-slot="tooltip-content"]');
    await expect(popup).toBeVisible();
    await popup.hover();
    await expect(popup).toBeVisible();
    const bounds = await popup.boundingBox();
    expect(bounds).not.toBeNull();
    if (bounds) {
        expect(bounds.x).toBeGreaterThanOrEqual(0);
        expect(bounds.x + bounds.width).toBeLessThanOrEqual(320);
    }
    await expectAccessible(page);
    await page.keyboard.press('Escape');
    await expect(popup).toBeHidden();
});

test('collapsible keeps expanded controls out of the closed tab order and exposes disabled state', async ({
    page,
}) => {
    const trigger = page.getByRole('button', { name: 'Show example details' });
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expectFocusRing(trigger);
    await expect(
        page.getByRole('region', { name: 'Synthetic expanded details' }),
    ).toBeVisible();
    await expectAccessible(page);
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Expanded note')).toBeFocused();
    await trigger.focus();
    await page.keyboard.press('Space');
    await expect(page.getByLabel('Expanded note')).toBeHidden();
    await expect(
        page.getByRole('button', { name: 'Unavailable disclosure' }),
    ).toBeDisabled();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'Open example sheet' }),
    ).toBeFocused();
});

test('sheet traps focus, hides background, supports Escape and restores focus at reflow widths', async ({
    page,
}) => {
    for (const width of [640, 320]) {
        await page.setViewportSize({ width, height: 900 });
        const trigger = page.getByRole('button', {
            name: 'Open example sheet',
        });
        await trigger.focus();
        await page.keyboard.press('Enter');
        const sheet = page.getByRole('dialog', { name: 'Example panel' });
        await expect(sheet).toHaveAccessibleDescription(
            'Synthetic content, without backend actions.',
        );
        await expect(page.getByLabel('Panel note')).toBeFocused();
        await expect(page.locator('[data-slot="sheet-overlay"]')).toBeVisible();
        await expect(page.locator('#root')).toHaveAttribute(
            'aria-hidden',
            'true',
        );
        await page
            .locator('#fixture-password')
            .evaluate((element) => element.focus());
        await expect(page.getByLabel('Panel note')).toBeFocused();
        await page.keyboard.press('Shift+Tab');
        await expect(
            sheet.getByRole('button', { name: 'Close', exact: true }),
        ).toBeFocused();
        await page.keyboard.press('Tab');
        await expect(page.getByLabel('Panel note')).toBeFocused();
        await expectAccessible(page);
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= innerWidth,
            ),
        ).toBe(true);
        await page.keyboard.press('Escape');
        await expect(sheet).toBeHidden();
        await expect(trigger).toBeFocused();
    }
});

test('dropdown radio and checkbox items expose selection without independent focus escape', async ({
    page,
}) => {
    const trigger = page.getByRole('button', {
        name: 'Example display options',
    });
    await trigger.focus();
    await page.keyboard.press('Enter');
    const compact = page.getByRole('menuitemradio', { name: 'Compact view' });
    await expect(compact).toBeChecked();
    await expect(compact).toBeFocused();
    await page.keyboard.press('ArrowDown');
    const comfortable = page.getByRole('menuitemradio', {
        name: 'Comfortable view',
    });
    await expect(comfortable).toBeFocused();
    await expect(
        page.getByRole('menuitemradio', { name: 'Unavailable density' }),
    ).toHaveAttribute('aria-disabled', 'true');
    await page.keyboard.press('Enter');
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(comfortable).toBeChecked();
    await page.keyboard.press('End');
    const dates = page.getByRole('menuitemcheckbox', {
        name: 'Show example dates',
    });
    await expect(dates).toBeFocused();
    await page.keyboard.press('Space');
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(dates).toBeChecked();
    await page.keyboard.press('Tab');
    await expect(page.getByRole('menu')).toBeVisible();
    await page
        .locator('#fixture-password')
        .evaluate((element) => element.focus());
    expect(
        await page
            .getByRole('menu')
            .evaluate((element) => element.contains(document.activeElement)),
    ).toBe(true);
    await expectAccessible(page, '[role="menu"]');
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
});

test('navigation disclosure and links preserve visible keyboard focus and Escape restoration', async ({
    page,
}) => {
    const tab = 'Tab';
    const reverseTab = 'Shift+Tab';
    await expectAccessible(page);
    const nav = page.getByRole('navigation', { name: 'Synthetic navigation' });
    const trigger = nav.getByRole('button', { name: 'Example links' });
    await trigger.focus();
    await page.keyboard.press('Space');
    await expect(trigger).toHaveAttribute('aria-expanded', 'true');
    await expectFocusRing(trigger);
    await page.keyboard.press('ArrowDown');
    await expect(
        nav.getByRole('link', { name: 'Example fields' }),
    ).toBeFocused();
    await expectFocusRing(nav.getByRole('link', { name: 'Example fields' }));
    await page.keyboard.press(tab);
    await expect(
        nav.getByRole('link', { name: 'Example controls' }),
    ).toBeFocused();
    await page.keyboard.press(reverseTab);
    await expect(
        nav.getByRole('link', { name: 'Example fields' }),
    ).toBeFocused();
    await page.keyboard.press(reverseTab);
    await expect(trigger).toBeFocused();
    await page.keyboard.press(tab);
    await expect(
        nav.getByRole('link', { name: 'Example fields' }),
    ).toBeFocused();
    await page.keyboard.press(tab);
    await expect(
        nav.getByRole('link', { name: 'Example controls' }),
    ).toBeFocused();
    await page.keyboard.press(tab);
    const routedFocus = await page.evaluate(() => ({
        tag: document.activeElement?.tagName,
        id: document.activeElement?.id,
        hidden: document.activeElement?.getAttribute('aria-hidden'),
    }));
    await expect(
        nav.getByRole('link', { name: 'Example password' }),
        JSON.stringify(routedFocus),
    ).toBeFocused();
    await trigger.focus();
    if ((await trigger.getAttribute('aria-expanded')) === 'false') {
        await page.keyboard.press('Space');
    }
    await page.keyboard.press('ArrowDown');
    await expect(
        nav.getByRole('link', { name: 'Example fields' }),
    ).toBeFocused();
    test.info().annotations.push({
        type: 'reviewed-axe-scope',
        description:
            'User-approved open NavigationMenu content scan: Radix 1.4.1 hidden focus proxy is outside this scope; bidirectional keyboard routing and whole-page before/after scans remain enforced.',
    });
    await expectAccessible(page, '[data-slot="navigation-menu-content"]');
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await expect(trigger).toHaveAttribute('aria-expanded', 'false');
    const link = nav.getByRole('link', { name: 'Example password' });
    await page.keyboard.press(tab);
    await expectFocusRing(link);
    await expectAccessible(page);
});

test('reduced-motion preference suppresses shared overlay motion while ordinary motion remains available', async ({
    page,
}) => {
    expect(
        await page.evaluate(
            () => matchMedia('(prefers-reduced-motion: reduce)').matches,
        ),
    ).toBe(true);
    for (const [triggerName, selector] of [
        ['Open example dialog', '[data-slot="dialog-content"]'],
        ['Open example sheet', '[data-slot="sheet-content"]'],
    ]) {
        await page.getByRole('button', { name: triggerName }).click();
        const overlay = page.locator(selector);
        await expect(overlay).toBeVisible();
        await expect(overlay).toHaveCSS('animation-name', 'none');
        await page.keyboard.press('Escape');
        await expect(overlay).toBeHidden();
    }
    await page.emulateMedia({ reducedMotion: 'no-preference' });
    await page.getByRole('button', { name: 'Open example sheet' }).click();
    await expect(page.locator('[data-slot="sheet-content"]')).not.toHaveCSS(
        'animation-name',
        'none',
    );
    await page.keyboard.press('Escape');
});
