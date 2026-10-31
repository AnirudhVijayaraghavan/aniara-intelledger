import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../', import.meta.url));
const cli = `${root}node_modules/@playwright/test/cli.js`;
const result = spawnSync(
    process.execPath,
    [cli, 'test', '--config=tests/Browser/probe.config.ts'],
    {
        cwd: root,
        encoding: 'utf8',
        timeout: 180_000,
        windowsHide: true,
        env: {
            ...process.env,
            NODE_OPTIONS: '--max-old-space-size=512 --max-semi-space-size=4',
            RAYON_NUM_THREADS: '1',
            ROLLDOWN_WORKER_THREADS: '1',
        },
    },
);
assert.ifError(result.error);
assert.equal(
    result.status,
    1,
    'The intentional accessibility violation must exit 1.',
);
assert.match(result.stdout, /button-name/);
assert.match(result.stdout, /intentional-unnamed-button/);
console.log(
    'Confirmed: the browser command exits 1 for the synthetic button-name violation.',
);

const unavailable = spawnSync(
    process.execPath,
    [cli, 'test', '--config=tests/Browser/probe.config.ts'],
    {
        cwd: root,
        encoding: 'utf8',
        timeout: 180_000,
        windowsHide: true,
        env: {
            ...process.env,
            PLAYWRIGHT_BROWSERS_PATH: `${root}.phpunit.cache/intentionally-unavailable-browser`,
            NODE_OPTIONS: '--max-old-space-size=512 --max-semi-space-size=4',
            RAYON_NUM_THREADS: '1',
            ROLLDOWN_WORKER_THREADS: '1',
        },
    },
);
assert.ifError(unavailable.error);
assert.equal(
    unavailable.status,
    1,
    'Missing browser tooling must fail, never count as a clean scan.',
);
assert.match(
    unavailable.stdout + unavailable.stderr,
    /Executable doesn't exist/,
);
console.log('Confirmed: missing browser tooling also exits 1.');
