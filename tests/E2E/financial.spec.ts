import AxeBuilder from '@axe-core/playwright';
import { expect, test as base } from '@playwright/test';
import type { Locator, Page } from '@playwright/test';
import { execFileSync } from 'node:child_process';
import { createHash } from 'node:crypto';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
type Household = {
    email: string;
    password: string;
    teamName: string;
    secondTeamName: string;
    dashboard: string;
    accounts: string;
    account: string;
    createTransaction: string;
    transactions: string;
    upload: string;
    secondAccounts: string;
    secondCreate: string;
    crossAccount: string;
    hiddenAccount: string;
    secondAccountName: string;
};
type Inspection = {
    transactions: number;
    revisions: number;
    balances: number;
    amount: string;
    batches: number;
    rows: number;
    committedRows: number;
    encryptedStaging: boolean;
};
function fixtures<T>(operation: 'seed' | 'inspect', identity: string): T {
    return JSON.parse(
        execFileSync('php', ['tests/FinancialE2EFixtures.php'], {
            cwd: root,
            input: JSON.stringify({ operation, case: identity }),
            encoding: 'utf8',
            timeout: 20_000,
            maxBuffer: 64 * 1024,
            windowsHide: true,
        }),
    ) as T;
}
const test = base.extend<{ household: Household; inspect: () => Inspection }>({
    household: async ({ page }, runTest, info) => {
        const identity = createHash('sha256')
            .update(info.testId)
            .digest('hex')
            .slice(0, 16);
        const household = fixtures<Household>('seed', identity);
        const errors: string[] = [];
        page.on('pageerror', () => errors.push('Application page error.'));
        page.on('response', (response) => {
            const path = new URL(response.url()).pathname;
            if (
                path.startsWith('/build/') &&
                (response.status() !== 200 ||
                    (path.endsWith('.js') &&
                        !/javascript/.test(
                            response.headers()['content-type'] ?? '',
                        )) ||
                    (path.endsWith('.css') &&
                        !/css/.test(response.headers()['content-type'] ?? '')))
            ) {
                errors.push(
                    `Asset response ${response.status()} ${response.headers()['content-type']}: ${path}`,
                );
            }
        });
        await page.route('**/*', async (route) => {
            if (
                new URL(route.request().url()).origin !==
                'http://127.0.0.1:4182'
            ) {
                errors.push(
                    'Unexpected external origin: ' +
                        new URL(route.request().url()).origin,
                );
                await route.abort();
                return;
            }
            await route.continue();
        });
        const response = await page.goto('/login');
        expect(response?.status()).toBe(200);
        expect(errors).toEqual([]);
        const rawCapability = process.env.INTELLEDGER_E2E_CONTEXT;
        if (!rawCapability) throw new Error('Live E2E capability required.');
        const capability = JSON.parse(rawCapability);
        expect(response?.headers()['x-intelledger-e2e-lease']).toBe(
            createHash('sha256').update(capability.receipt.nonce).digest('hex'),
        );
        try {
            await expect(page.getByLabel('Email address')).toBeVisible();
        } catch {
            const startup = await page.evaluate(() => ({
                title: document.title,
                appChildren: document.querySelector('#app')?.childElementCount,
                scripts: Array.from(
                    document.querySelectorAll<HTMLScriptElement>('script[src]'),
                ).map((script) => new URL(script.src).pathname),
            }));
            throw new Error(
                'Inertia startup failed: ' +
                    JSON.stringify({ errors, startup }),
            );
        }
        await runTest(household);
        expect(errors).toEqual([]);
    },
    inspect: async ({ household: _household }, runTest, info) => {
        const identity = createHash('sha256')
            .update(info.testId)
            .digest('hex')
            .slice(0, 16);
        await runTest(() => fixtures<Inspection>('inspect', identity));
    },
});
async function login(page: Page, household: Household): Promise<void> {
    await page.getByLabel('Email address').fill(household.email);
    await page.getByLabel('Password', { exact: true }).fill(household.password);
    await page.getByRole('button', { name: 'Log in', exact: true }).click();
    await expect(page).toHaveURL(
        new URL(household.dashboard, 'http://127.0.0.1:4182').href,
    );
    await expect(
        page.getByRole('link', { name: 'Accounts', exact: true }).first(),
    ).toBeVisible();
}
async function accessible(page: Page, area: Locator): Promise<void> {
    await page.evaluate(async () => {
        await document.fonts.ready;
    });
    const results = await new AxeBuilder({ page })
        .include(
            await area.evaluate((element) => {
                element.setAttribute('data-e2e-axe', 'active');
                return '[data-e2e-axe="active"]';
            }),
        )
        .withTags(['wcag2a', 'wcag2aa', 'wcag21a', 'wcag21aa', 'wcag22aa'])
        .analyze();
    expect(
        results.violations.map(({ id, impact, nodes }) => ({
            id,
            impact,
            targets: nodes.map(({ target }) => target),
        })),
    ).toEqual([]);
    if (results.incomplete.length)
        test.info().annotations.push({
            type: 'axe-manual-review',
            description: results.incomplete.map(({ id }) => id).join(', '),
        });
    await area.evaluate((element) => element.removeAttribute('data-e2e-axe'));
}

test('real login, authenticated navigation, keyboard logout and session separation', async ({
    page,
    household,
    browser,
}) => {
    await accessible(page, page.locator('form').first());
    await page.getByLabel('Password', { exact: true }).focus();
    await page.keyboard.press('Tab');
    await expect(
        page.getByRole('button', { name: 'Show password', exact: true }),
    ).toBeFocused();
    await login(page, household);
    await page
        .getByRole('link', { name: 'Accounts', exact: true })
        .first()
        .click();
    await expect(page).toHaveURL(
        new URL(household.accounts, 'http://127.0.0.1:4182').href,
    );
    const guest = await browser.newContext();
    try {
        const denied = await guest.request.get(
            'http://127.0.0.1:4182' + household.account,
            { maxRedirects: 0 },
        );
        expect(denied.status()).toBe(302);
        expect(denied.headers().location).toContain('/login');
    } finally {
        await guest.close();
    }
    await page.locator('[data-test="sidebar-menu-button"]').click();
    const logout = page.getByRole('menuitem', { name: 'Log out' });
    await logout.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL('http://127.0.0.1:4182/');
    await page.goto(household.account);
    await expect(page).toHaveURL(/\/login$/);
});

test('household switching preserves member permissions and rejects cross-Team records without disclosure', async ({
    page,
    household,
}) => {
    await login(page, household);
    await page
        .getByRole('link', { name: 'Accounts', exact: true })
        .first()
        .click();
    await expect(page).toHaveURL(
        new URL(household.accounts, 'http://127.0.0.1:4182').href,
    );
    const switcher = page
        .locator('[data-test="team-switcher-trigger"]')
        .first();
    await switcher.focus();
    await page.keyboard.press('Enter');
    const secondary = page.getByRole('menuitem', {
        name: household.secondTeamName,
        exact: true,
    });
    await secondary.focus();
    await page.keyboard.press('Enter');
    await expect(page).toHaveURL(
        new URL(household.secondAccounts, 'http://127.0.0.1:4182').href,
    );
    await expect(
        page.getByRole('link', {
            name: household.secondAccountName,
            exact: true,
        }),
    ).toBeVisible();
    expect((await page.request.get(household.secondCreate)).status()).toBe(403);
    for (const [url, status] of [
        [household.crossAccount, 404],
        [household.hiddenAccount, 403],
    ] as const) {
        const response = await page.request.get(url);
        expect(response.status()).toBe(status);
        expect(await response.text()).not.toContain('Hidden Account Sentinel');
        expect(await response.text()).not.toContain(
            'Hidden Household Sentinel',
        );
    }
});

test('actual account details render precise recorded balances and observation dates', async ({
    page,
    household,
    inspect,
}) => {
    await login(page, household);
    const response = await page.goto(household.account);
    if (!response) throw new Error('Account response required.');
    const balance = page.getByRole('region', {
        name: 'Current recorded balance',
    });
    await expect(balance).toContainText('1234.12345678');
    await expect(balance).toContainText('USD');
    await expect(balance.locator('time')).toHaveAttribute(
        'datetime',
        '2020-01-02',
    );
    await accessible(page, balance);
    const props = await page.evaluate(
        (html) => {
            const document = new DOMParser().parseFromString(html, 'text/html');
            const data = document.querySelector(
                'script[data-page="app"]',
            )?.textContent;
            if (!data) throw new Error('Real Inertia initial page required.');
            return JSON.parse(data).props;
        },
        await response.text(),
    );
    expect(props.currentBalance.amount).toBe('1234.12345678');
    expect(inspect()).toMatchObject({
        balances: 1,
        amount: '1234.12345678',
        transactions: 1,
        revisions: 1,
    });
});

test('server-side transaction validation preserves help, accessible errors and financial records', async ({
    page,
    household,
    inspect,
}) => {
    await login(page, household);
    await page.goto(household.createTransaction);
    const amount = page.getByLabel('Account movement (USD)', { exact: true });
    await amount.fill('not-a-decimal');
    await page
        .getByRole('button', { name: 'Record transaction', exact: true })
        .click();
    await expect(amount).toHaveAttribute('aria-invalid', 'true');
    await expect(page.locator('#create-amount-error')).toBeVisible();
    await expect(amount).toHaveAttribute(
        'aria-describedby',
        'create-amount-help create-amount-error',
    );
    await expect(amount).toHaveAccessibleDescription(
        /Positive increases value owned/,
    );
    await amount.focus();
    await page.keyboard.press('Tab');
    await expect(
        page.getByLabel('Transaction date', { exact: true }),
    ).toBeFocused();
    await accessible(page, page.locator('form'));
    expect(inspect()).toMatchObject({
        transactions: 1,
        revisions: 1,
        balances: 1,
        amount: '1234.12345678',
    });
});

test('browser multipart CSV upload stages encrypted rows idempotently without committing transactions', async ({
    page,
    household,
    inspect,
}) => {
    await login(page, household);
    await page.goto(household.account);
    const unprotected = await page.request.post(household.upload, {
        headers: { Accept: 'application/json' },
    });
    expect(unprotected.status()).toBe(419);
    const upload = () =>
        page.evaluate(async (url) => {
            const data = new FormData();
            data.set(
                'file',
                new File(
                    [
                        'Date,Amount,Description\n2020-01-02,-12.00000001,Synthetic CSV row\n',
                    ],
                    'synthetic.csv',
                    { type: 'text/csv' },
                ),
            );
            const token = document.cookie
                .split('; ')
                .find((part) => part.startsWith('XSRF-TOKEN='))
                ?.slice('XSRF-TOKEN='.length);
            if (!token) throw new Error('Real CSRF cookie required.');
            const response = await fetch(url, {
                method: 'POST',
                credentials: 'same-origin',
                body: data,
                headers: {
                    Accept: 'application/json',
                    'X-XSRF-TOKEN': decodeURIComponent(token),
                },
            });
            return { status: response.status, body: await response.json() };
        }, household.upload);
    const first = await upload();
    expect(first.status).toBe(201);
    expect(first.body).toMatchObject({
        reused: false,
        batch: { status: 'mapping', total_rows: 1, processed_rows: 0 },
    });
    expect(first.body.batch).not.toHaveProperty('file_path');
    const second = await upload();
    expect(second.status).toBe(200);
    expect(second.body).toMatchObject({
        reused: true,
        batch: { id: first.body.batch.id },
    });
    expect(inspect()).toMatchObject({
        transactions: 1,
        revisions: 1,
        balances: 1,
        amount: '1234.12345678',
        batches: 1,
        rows: 1,
        committedRows: 0,
        encryptedStaging: true,
    });
});
