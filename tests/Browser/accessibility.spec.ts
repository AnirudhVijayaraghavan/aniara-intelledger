import { expect } from '@playwright/test';
import { expectAccessible, test } from './accessibility';

test('initial components and button/alert states have accessible names and contrast', async ({
    page,
}) => {
    await expect(
        page.getByRole('button', { name: 'Unavailable example', exact: true }),
    ).toBeDisabled();
    await expect(
        page.getByRole('button', { name: 'Saving example' }),
    ).toHaveAttribute('aria-busy', 'true');
    await expect(page.getByRole('alert')).toContainText(
        'No records were changed.',
    );
    await expectAccessible(page);
    await page
        .getByRole('button', { name: 'Destructive example', exact: true })
        .hover();
    await expectAccessible(page);
});

test('password toggle works by keyboard, retains the input, and never submits the form', async ({
    page,
}) => {
    const password = page.getByLabel('Synthetic password', { exact: true });
    await password.focus();
    await page.keyboard.press('Tab');
    const show = page
        .getByRole('button', { name: 'Show password', exact: true })
        .first();
    await expect(show).toBeFocused();
    expect(
        await show.evaluate((element) => element.matches(':focus-visible')),
    ).toBe(true);
    expect(
        await show.evaluate((element) => getComputedStyle(element).boxShadow),
    ).not.toBe('none');
    await password.evaluate((element) =>
        element.setAttribute('data-original-node', 'yes'),
    );
    await page.keyboard.press('Enter');
    await expect(password).toHaveAttribute('type', 'text');
    await expect(password).toHaveAttribute('data-original-node', 'yes');
    await expect(password).toHaveAttribute('autocomplete', 'current-password');
    await expect(password).toHaveAttribute('name', 'password');
    await expect(password).toHaveValue('synthetic-only');
    await expect(
        page.getByRole('button', { name: 'Hide password' }),
    ).toBeFocused();
    await expectAccessible(page);
    await page.keyboard.press('Space');
    await expect(password).toHaveAttribute('type', 'password');
    await expect(page.getByLabel('Submission count')).toHaveText('0');
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'Submit synthetic form' }),
    ).toBeFocused();
    await page.keyboard.press('Enter');
    await expect(page.getByLabel('Submission count')).toHaveText('1');
    await expect(page.getByLabel('Disabled password')).toBeDisabled();
    await expect(
        page.locator('[aria-controls="disabled-password"]'),
    ).toBeDisabled();
    await page.getByRole('button', { name: 'Submit synthetic form' }).focus();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'After disabled password' }),
    ).toBeFocused();
});

test('transaction error descriptions preserve help and clear without dangling references', async ({
    page,
}) => {
    const amount = page.getByLabel('Account movement (USD)');
    await expect(amount).toHaveAccessibleDescription(
        /Positive increases the amount owed.*Enter a valid recorded amount/,
    );
    await expect(amount).toHaveAttribute('aria-invalid', 'true');
    for (const [id, description] of [
        ['date', 'Check the transaction date.'],
        ['posted', 'Check the posting date.'],
        ['kind', 'Check the kind.'],
        ['category', 'Check the category.'],
        ['payee', 'Check the payee.'],
        ['description', 'Check the description.'],
        ['excluded', 'Check the exclusion.'],
        ['reason', 'Check the reason.'],
    ]) {
        await expect(
            page.locator(`#fixture-${id}`),
        ).toHaveAccessibleDescription(description);
        await expect(page.locator(`#fixture-${id}`)).toHaveAttribute(
            'aria-invalid',
            'true',
        );
    }
    await page.getByRole('button', { name: 'Clear validation errors' }).click();
    await expect(amount).toHaveAccessibleDescription(
        /Positive increases the amount owed/,
    );
    await expect(amount).toHaveAttribute(
        'aria-describedby',
        'fixture-amount-help',
    );
    await expect(amount).toHaveAttribute('aria-invalid', 'false');
    expect(
        await page
            .locator('[aria-describedby]')
            .evaluateAll((elements) =>
                elements.every((element) =>
                    (element.getAttribute('aria-describedby') ?? '')
                        .split(/\s+/)
                        .every((id) => document.getElementById(id)),
                ),
            ),
    ).toBe(true);
    await expectAccessible(page);
});

test('dialog traps keyboard focus, handles Escape, and restores trigger focus', async ({
    page,
}) => {
    const trigger = page.getByRole('button', { name: 'Open example dialog' });
    await trigger.focus();
    await page.keyboard.press('Enter');
    const dialog = page.getByRole('dialog', {
        name: 'Review synthetic observation',
    });
    await expect(dialog).toHaveAccessibleDescription(
        'This example cannot save financial records.',
    );
    await expect(page.getByLabel('Review note')).toBeFocused();
    await expect(page.locator('#root')).toHaveAttribute('aria-hidden', 'true');
    await expect(page.locator('[data-slot="dialog-overlay"]')).toBeVisible();
    await page
        .locator('#fixture-password')
        .evaluate((element) => element.focus());
    await expect(page.getByLabel('Review note')).toBeFocused();
    await expectAccessible(page);
    await page.keyboard.press('Shift+Tab');
    await expect(
        dialog.getByRole('button', { name: 'Close', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(page.getByLabel('Review note')).toBeFocused();
    await page.keyboard.press('Escape');
    await expect(dialog).toBeHidden();
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter');
    await page.getByRole('button', { name: 'Finish review' }).click();
    await expect(trigger).toBeFocused();
});

test('select supports keyboard options, selection, disabled items, and focus restoration', async ({
    page,
}) => {
    const select = page.getByRole('combobox', { name: 'Display currency' });
    await select.focus();
    await page.keyboard.press('Enter');
    await expect(page.getByRole('listbox')).toBeVisible();
    await expect(
        page.getByRole('option', { name: 'Unavailable currency' }),
    ).toHaveAttribute('aria-disabled', 'true');
    await page.keyboard.press('Home');
    await expect(
        page.getByRole('option', { name: 'USD', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(
        page.getByRole('option', { name: 'INR', exact: true }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('option', { name: 'INR', exact: true }),
    ).toBeFocused();
    await page
        .locator('#fixture-password')
        .evaluate((element) => element.focus());
    await expect(
        page.getByRole('option', { name: 'INR', exact: true }),
    ).toBeFocused();
    await expectAccessible(page, '[role="listbox"]');
    await page.keyboard.press('Enter');
    await expect(select).toBeFocused();
    await expect(select).toContainText('INR');
    await expect(page.getByLabel('Selected currency')).toHaveText('INR');
    await page.keyboard.press('Enter');
    await page.keyboard.press('Escape');
    await expect(select).toBeFocused();
    await expectAccessible(page);
});

test('dropdown supports keyboard navigation, disabled actions, activation and Escape', async ({
    page,
}) => {
    const trigger = page.getByRole('button', { name: 'Example actions' });
    await trigger.focus();
    await page.keyboard.press('Enter');
    await expect(
        page.getByRole('menuitem', { name: 'Review example' }),
    ).toBeFocused();
    await page.keyboard.press('ArrowDown');
    await expect(
        page.getByRole('menuitem', { name: 'Discard example' }),
    ).toBeFocused();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('menuitem', { name: 'Discard example' }),
    ).toBeFocused();
    await page
        .locator('#fixture-password')
        .evaluate((element) => element.focus());
    await expect(
        page.getByRole('menuitem', { name: 'Discard example' }),
    ).toBeFocused();
    await expectAccessible(page, '[role="menu"]');
    await page.keyboard.press('Enter');
    await expect(page.getByLabel('Selected action')).toHaveText('Discard');
    await expect(trigger).toBeFocused();
    await page.keyboard.press('Enter');
    await page.keyboard.press('Escape');
    await expect(trigger).toBeFocused();
    await expectAccessible(page);
});

test('narrow and 200 percent equivalent reflow retains controls without horizontal scrolling', async ({
    page,
}) => {
    // A 1280 CSS-pixel viewport at 200% browser zoom exposes 640 CSS pixels.
    // Changing deviceScaleFactor alone would not test layout reflow.
    for (const width of [640, 320]) {
        await page.setViewportSize({ width, height: 900 });
        expect(
            await page.evaluate(
                () => document.documentElement.scrollWidth <= window.innerWidth,
            ),
        ).toBe(true);
        await expect(
            page.getByRole('button', { name: 'Submit synthetic form' }),
        ).toBeVisible();
        await expectAccessible(page);
        await page.getByRole('button', { name: 'Open example dialog' }).click();
        const dialog = page.getByRole('dialog');
        const box = await dialog.boundingBox();
        expect(box).not.toBeNull();
        if (box) {
            expect(box.x).toBeGreaterThanOrEqual(0);
            expect(box.x + box.width).toBeLessThanOrEqual(width);
        }
        await expectAccessible(page);
        await page.keyboard.press('Escape');
    }
});
