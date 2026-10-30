import assert from 'node:assert/strict';
import { spawnSync } from 'node:child_process';
import { randomUUID } from 'node:crypto';
import { readFileSync, readdirSync, unlinkSync, writeFileSync } from 'node:fs';
import { join } from 'node:path';
import test from 'node:test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('../../../', import.meta.url));
const vitePlus = join(root, 'node_modules/vite-plus/bin/vp');

function runVitePlus(...args) {
    const result = spawnSync(process.execPath, [vitePlus, ...args], {
        cwd: root,
        encoding: 'utf8',
        timeout: 30_000,
    });
    assert.ifError(result.error);
    return result;
}

void test('frontend lint covers every application-owned module and enforces React hooks', () => {
    const configuration = runVitePlus('lint', '--print-config');
    assert.equal(configuration.status, 0, configuration.stderr);
    const config = JSON.parse(configuration.stdout);
    for (const plugin of ['unicorn', 'typescript', 'oxc', 'react']) {
        assert.ok(config.plugins.includes(plugin), `Missing ${plugin} plugin`);
    }
    assert.equal(config.options.typeAware, true);
    assert.equal(config.options.denyWarnings, true);
    for (const rule of ['react/rules-of-hooks', 'react/exhaustive-deps']) {
        assert.ok(['error', 'deny'].includes(config.rules[rule]));
    }

    const discovery = runVitePlus('lint', '--debug', 'files', '--threads=1');
    assert.equal(discovery.status, 0, discovery.stderr);
    const lintedFiles = new Set(
        discovery.stdout
            .trim()
            .split(/\r?\n/)
            .map((path) => path.replaceAll('\\', '/')),
    );
    const sourceFiles = readdirSync(join(root, 'resources/js'), {
        recursive: true,
    }).map((path) => path.replaceAll('\\', '/'));
    for (const path of sourceFiles) {
        if (
            /\.(?:ts|tsx|mjs)$/.test(path) &&
            !/^(?:actions|routes|wayfinder)\//.test(path)
        ) {
            assert.ok(
                lintedFiles.has(`resources/js/${path}`),
                `${path} is not linted`,
            );
        }
    }
});

void test('UI lint and format checks reject violations without rewriting the file', () => {
    const path = join(
        root,
        'resources/js/components/ui',
        `quality-probe-${randomUUID()}.tsx`,
    );
    const source =
        'import {useState} from "react";\n' +
        'export function QualityProbe({enabled}:{enabled:boolean}) {\n' +
        'if(enabled){useState(0);}\nreturn <div/>;\n}\n';
    writeFileSync(path, source, { flag: 'wx' });
    try {
        const formatting = runVitePlus('fmt', path, '--check', '--threads=1');
        assert.equal(formatting.status, 1, formatting.stderr);
        assert.match(
            formatting.stdout + formatting.stderr,
            /Format issues found/,
        );
        assert.equal(readFileSync(path, 'utf8'), source);

        const lint = runVitePlus('lint', path, '--threads=1', '--format=json');
        assert.equal(lint.status, 1, lint.stderr);
        assert.match(
            lint.stdout + lint.stderr,
            /react\(rules-of-hooks\)|react\/rules-of-hooks/,
        );
        assert.equal(readFileSync(path, 'utf8'), source);
    } finally {
        unlinkSync(path);
    }
});
