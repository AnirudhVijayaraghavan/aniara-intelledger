import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { after, before, test } from 'node:test';
import { createElement, Fragment } from 'react';
import { renderToStaticMarkup } from 'react-dom/server';
import { loadReactComponents } from '../../../tests/Support/react-components.mjs';

let components;
let cleanup;
before(async () => {
    ({ components, cleanup } = await loadReactComponents());
});
after(async () => {
    await cleanup?.();
});

/** Read attributes from rendered React HTML, not from component source. Fixtures contain no markup in attributes. */
function elements(html, tag) {
    return [...html.matchAll(new RegExp(`<${tag}\\b([^>]*)>`, 'g'))].map(
        (match) =>
            Object.fromEntries(
                [...match[1].matchAll(/([\w:-]+)(?:="([^"]*)")?/g)].map(
                    (attribute) => [attribute[1], attribute[2] ?? ''],
                ),
            ),
    );
}

function assertError(html, id, message) {
    const controls = [...elements(html, 'input'), ...elements(html, 'button')];
    const control = controls.find((element) => element.id === id);
    assert.ok(control, `Missing rendered control ${id}`);
    assert.equal(control['aria-invalid'], 'true');
    const descriptionIds = control['aria-describedby']?.split(' ') ?? [];
    assert.ok(
        descriptionIds.includes(`${id}-error`),
        `Missing association for ${id}`,
    );
    assert.equal(
        elements(html, 'p').filter((element) => element.id === `${id}-error`)
            .length,
        1,
    );
    assert.ok(
        html.includes(message),
        `Missing rendered error message for ${id}`,
    );
    assert.ok(
        elements(html, 'label').some((label) => label.for === id),
        `Missing label for ${id}`,
    );
}

void test('password visibility has a named keyboard-operable button without changing password-manager attributes', () => {
    const html = renderToStaticMarkup(
        createElement(components.PasswordInput, {
            id: 'password',
            name: 'password',
            autoComplete: 'current-password',
            required: true,
            defaultValue: 'synthetic-only',
            tabIndex: 2,
            'aria-describedby': 'password-help',
        }),
    );
    const [input] = elements(html, 'input');
    const [button] = elements(html, 'button');
    assert.equal(input.type, 'password');
    assert.equal(input.name, 'password');
    assert.equal(input.autoComplete ?? input.autocomplete, 'current-password');
    assert.equal(input['aria-describedby'], 'password-help');
    assert.equal(input.value, 'synthetic-only');
    assert.equal(button.type, 'button');
    assert.equal(button['aria-label'], 'Show password');
    assert.equal(button['aria-controls'], input.id);
    assert.equal(button.tabindex, '2');
});

void test('disabled passwords do not expose an operable visibility control', () => {
    const html = renderToStaticMarkup(
        createElement(components.PasswordInput, { disabled: true }),
    );
    assert.ok(Object.hasOwn(elements(html, 'input')[0], 'disabled'));
    assert.ok(Object.hasOwn(elements(html, 'button')[0], 'disabled'));
});

void test('passwords without caller IDs have distinct controls and natural tab order', () => {
    const html = renderToStaticMarkup(
        createElement(
            Fragment,
            null,
            createElement(components.PasswordInput, { name: 'password' }),
            createElement(components.PasswordInput, { name: 'confirmation' }),
        ),
    );
    const inputs = elements(html, 'input');
    const buttons = elements(html, 'button');
    assert.notEqual(inputs[0].id, inputs[1].id);
    for (const [index, button] of buttons.entries()) {
        assert.equal(button['aria-controls'], inputs[index].id);
        assert.equal(button.tabindex, undefined);
    }
});

void test('transaction financial errors keep the explanatory amount description and stable unique IDs', () => {
    const data = {
        amount: '123.00000001',
        transaction_date: '2026-10-01',
        posted_date: '2026-10-02',
    };
    const props = {
        data,
        onChange() {},
        errors: {
            amount: 'Invalid amount',
            transaction_date: 'Invalid date',
            posted_date: 'Invalid posting date',
        },
        currency: 'USD',
        isLiability: true,
        includePosted: true,
    };
    const html = renderToStaticMarkup(
        createElement(
            Fragment,
            null,
            createElement(components.TransactionFinancialFields, {
                ...props,
                prefix: 'create',
            }),
            createElement(components.TransactionFinancialFields, {
                ...props,
                prefix: 'post',
            }),
        ),
    );
    for (const prefix of ['create', 'post']) {
        assertError(html, `${prefix}-amount`, 'Invalid amount');
        assertError(html, `${prefix}-date`, 'Invalid date');
        assertError(html, `${prefix}-posted`, 'Invalid posting date');
        const amount = elements(html, 'input').find(
            (input) => input.id === `${prefix}-amount`,
        );
        assert.deepEqual(amount['aria-describedby'].split(' '), [
            `${prefix}-amount-help`,
            `${prefix}-amount-error`,
        ]);
        assert.ok(
            elements(html, 'p').some((p) => p.id === `${prefix}-amount-help`),
        );
        assert.equal(amount.value, data.amount);
    }
    const ids = [...html.matchAll(/\bid="([^"]+)"/g)].map((match) => match[1]);
    assert.equal(new Set(ids).size, ids.length);
});

void test('valid transaction fields do not reference missing errors and posting fields remain conditional', () => {
    const html = renderToStaticMarkup(
        createElement(components.TransactionFinancialFields, {
            prefix: 'edit',
            data: {
                amount: '-1.00000001',
                transaction_date: '2026-10-01',
                posted_date: '',
            },
            onChange() {},
            errors: {},
            currency: 'INR',
            isLiability: false,
            includePosted: false,
        }),
    );
    const inputs = elements(html, 'input');
    assert.deepEqual(
        inputs.map((input) => input.id),
        ['edit-amount', 'edit-date'],
    );
    assert.equal(inputs[0]['aria-describedby'], 'edit-amount-help');
    assert.equal(inputs[0]['aria-invalid'], 'false');
    assert.equal(inputs[1]['aria-describedby'], undefined);
});

void test('transaction metadata errors associate labels, native inputs, checkbox and Radix choices', () => {
    const errors = {
        kind: 'Invalid kind',
        category_id: 'Invalid category',
        payee_id: 'Invalid payee',
        description: 'Invalid description',
        excluded_from_analytics: 'Invalid exclusion',
        reason: 'Invalid reason',
    };
    const html = renderToStaticMarkup(
        createElement(components.TransactionMetadataFields, {
            prefix: 'classify',
            data: {
                kind: 'unclassified',
                category_id: '',
                payee_id: '',
                description: '',
                excluded_from_analytics: false,
                reason: '',
            },
            onChange() {},
            errors,
            categories: [],
            payees: [],
            team: {
                id: 1,
                slug: 'synthetic-household',
                name: 'Synthetic household',
            },
        }),
    );
    for (const [field, id] of [
        ['kind', 'kind'],
        ['category_id', 'category'],
        ['payee_id', 'payee'],
        ['description', 'description'],
        ['excluded_from_analytics', 'excluded'],
        ['reason', 'reason'],
    ]) {
        assertError(html, `classify-${id}`, errors[field]);
    }
});

void test('error-free financial choices do not leave dangling error descriptions', () => {
    const html = renderToStaticMarkup(
        createElement(components.FinancialChoice, {
            id: 'account',
            label: 'Account',
            value: 'synthetic',
            options: [{ value: 'synthetic', label: 'Synthetic account' }],
            onChange() {},
        }),
    );
    assert.equal(
        elements(html, 'button').find((button) => button.id === 'account')[
            'aria-describedby'
        ],
        undefined,
    );
});

function themeTokens() {
    const css = readFileSync(
        new URL('../../css/app.css', import.meta.url),
        'utf8',
    );
    const tokens = {};
    for (const match of css.matchAll(
        /(:root,\s*\.ledger-public|\.dark)\s*\{([^}]+)\}/g,
    )) {
        tokens[match[1] === '.dark' ? 'dark' : 'light'] = Object.fromEntries(
            [...match[2].matchAll(/--([\w-]+):\s*(#[\da-f]{6})/gi)].map(
                (token) => [token[1], token[2]],
            ),
        );
    }
    assert.deepEqual(Object.keys(tokens), ['light', 'dark']);
    return tokens;
}

function rgb(hex) {
    assert.match(hex, /^#[\da-f]{6}$/i);
    return hex
        .slice(1)
        .match(/../g)
        .map((channel) => parseInt(channel, 16));
}

function blend(foreground, background, alpha) {
    return rgb(foreground).map(
        (channel, index) =>
            channel * alpha + rgb(background)[index] * (1 - alpha),
    );
}

function luminance(color) {
    const channels = (typeof color === 'string' ? rgb(color) : color).map(
        (channel) => {
            const srgb = channel / 255;
            return srgb <= 0.04045
                ? srgb / 12.92
                : ((srgb + 0.055) / 1.055) ** 2.4;
        },
    );
    return channels[0] * 0.2126 + channels[1] * 0.7152 + channels[2] * 0.0722;
}

function contrast(a, b) {
    return (
        (Math.max(luminance(a), luminance(b)) + 0.05) /
        (Math.min(luminance(a), luminance(b)) + 0.05)
    );
}

void test('contrast calculations match independent WCAG reference values', () => {
    assert.equal(contrast('#000000', '#ffffff'), 21);
    assert.equal(contrast('#ffffff', '#ffffff'), 1);
    assert.ok(Math.abs(contrast('#777777', '#ffffff') - 4.478089) < 0.000001);
});

for (const theme of ['light', 'dark']) {
    void test(`${theme} semantic foregrounds, controls and hover states meet contrast thresholds without rounding`, () => {
        const t = themeTokens()[theme];
        assert.ok(
            contrast(t['primary-text'], t.secondary) >= 4.5,
            `${theme} link on secondary surface`,
        );
        assert.ok(
            contrast(t['muted-foreground'], t.muted) >= 4.5,
            `${theme} placeholder fill`,
        );
        assert.ok(
            contrast(t['muted-foreground'], t.secondary) >= 4.5,
            `${theme} placeholder hover fill`,
        );
        for (const surface of ['background', 'card', 'popover', 'muted']) {
            for (const color of [
                'foreground',
                'primary-text',
                'destructive',
                'muted-foreground',
            ]) {
                assert.ok(
                    contrast(t[color], t[surface]) >= 4.5,
                    `${theme} ${color}/${surface}: ${contrast(t[color], t[surface])}`,
                );
            }
            for (const color of ['input', 'ring']) {
                assert.ok(
                    contrast(t[color], t[surface]) >= 3,
                    `${theme} ${color}/${surface}`,
                );
            }
            for (const role of ['primary', 'destructive']) {
                assert.ok(
                    contrast(t[`${role}-foreground`], t[role]) >= 4.5,
                    `${theme} ${role} fill`,
                );
                assert.ok(
                    contrast(
                        t[`${role}-foreground`],
                        blend(t[role], t[surface], 0.9),
                    ) >= 4.5,
                    `${theme} ${role} hover`,
                );
            }
        }
        // Radix menus render on their popover surface, not arbitrary field containers.
        assert.ok(
            contrast(t.destructive, blend(t.destructive, t.popover, 0.1)) >=
                4.5,
            `${theme} destructive menu focus`,
        );
    });
}

void test('destructive component markup uses the matching filled versus unfilled text roles', () => {
    const button = renderToStaticMarkup(
        createElement(
            components.Button,
            { variant: 'destructive' },
            'Delete synthetic item',
        ),
    );
    const badge = renderToStaticMarkup(
        createElement(components.Badge, { variant: 'destructive' }, 'Error'),
    );
    const alert = renderToStaticMarkup(
        createElement(
            components.Alert,
            { variant: 'destructive' },
            createElement(
                components.AlertDescription,
                null,
                'Synthetic validation failure',
            ),
        ),
    );
    assert.ok(
        elements(button, 'button')[0]
            .class.split(' ')
            .includes('text-destructive-foreground'),
    );
    assert.ok(
        elements(badge, 'span')[0]
            .class.split(' ')
            .includes('text-destructive-foreground'),
    );
    assert.ok(
        elements(alert, 'div')[0].class.split(' ').includes('text-destructive'),
    );
});

void test('rendered link buttons use the accessible text token and input focus rings are opaque', () => {
    const button = renderToStaticMarkup(
        createElement(components.Button, { variant: 'link' }, 'Synthetic link'),
    );
    assert.ok(
        elements(button, 'button')[0]
            .class.split(' ')
            .includes('text-primary-text'),
    );
    const html = renderToStaticMarkup(
        createElement(components.TransactionFinancialFields, {
            prefix: 'focus',
            data: { amount: '', transaction_date: '', posted_date: '' },
            onChange() {},
            errors: {},
            currency: 'USD',
            isLiability: false,
            includePosted: true,
        }),
    );
    for (const input of elements(html, 'input')) {
        assert.ok(input.class.split(' ').includes('focus-visible:ring-ring'));
        assert.ok(
            input.class.split(' ').includes('aria-invalid:ring-destructive'),
        );
    }
});

void test('rendered financial choices use readable placeholder surfaces and opaque focus rings', () => {
    const html = renderToStaticMarkup(
        createElement(components.FinancialChoice, {
            id: 'choice',
            label: 'Synthetic choice',
            value: '',
            emptyLabel: 'Not selected',
            options: [],
            onChange() {},
        }),
    );
    const trigger = elements(html, 'button').find(
        (button) => button.id === 'choice',
    );
    for (const utility of [
        'dark:bg-muted',
        'dark:hover:bg-secondary',
        'focus-visible:ring-ring',
        'aria-invalid:ring-destructive',
    ]) {
        assert.ok(trigger.class.split(' ').includes(utility));
    }
});
