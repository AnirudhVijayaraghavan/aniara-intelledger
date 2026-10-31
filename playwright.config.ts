import { defineConfig } from '@playwright/test';
import { fileURLToPath } from 'node:url';

const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    testDir: `${root}tests/Browser`,
    testMatch: ['accessibility.spec.ts', 'shared-primitives.spec.ts'],
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: true,
    timeout: 30_000,
    globalTimeout: 600_000,
    expect: { timeout: 5_000 },
    reporter: 'line',
    outputDir: `${root}.phpunit.cache/playwright-results`,
    use: {
        baseURL: 'http://127.0.0.1:4178',
        browserName: 'chromium',
        viewport: { width: 1280, height: 900 },
        locale: 'en-US',
        timezoneId: 'UTC',
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
        actionTimeout: 5_000,
        navigationTimeout: 10_000,
    },
    projects: [
        { name: 'chromium-light', use: { colorScheme: 'light' } },
        { name: 'chromium-dark', use: { colorScheme: 'dark' } },
        {
            name: 'firefox-light',
            use: { browserName: 'firefox', colorScheme: 'light' },
        },
        {
            name: 'firefox-dark',
            use: { browserName: 'firefox', colorScheme: 'dark' },
        },
        {
            name: 'webkit-light',
            use: { browserName: 'webkit', colorScheme: 'light' },
        },
        {
            name: 'webkit-dark',
            use: { browserName: 'webkit', colorScheme: 'dark' },
        },
    ],
    webServer: {
        cwd: root,
        command: 'node tests/Support/browser-harness.mjs',
        url: 'http://127.0.0.1:4178',
        reuseExistingServer: false,
        timeout: 90_000,
        env: {
            NODE_OPTIONS: '--max-old-space-size=512 --max-semi-space-size=4',
            ROLLDOWN_WORKER_THREADS: '1',
            RAYON_NUM_THREADS: '1',
        },
    },
});
