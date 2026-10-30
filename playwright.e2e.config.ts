import { defineConfig } from '@playwright/test';
import { fileURLToPath } from 'node:url';

if (!process.env.INTELLEDGER_E2E_CONTEXT) {
    throw new Error('Use the explicitly opted-in test:e2e lease owner.');
}
const root = fileURLToPath(new URL('.', import.meta.url));

export default defineConfig({
    testDir: `${root}tests/E2E`,
    testMatch: 'financial.spec.ts',
    outputDir: `${root}.phpunit.cache/financial-e2e-results`,
    fullyParallel: false,
    workers: 1,
    retries: 0,
    forbidOnly: true,
    timeout: 45_000,
    globalTimeout: 600_000,
    expect: { timeout: 8_000 },
    reporter: 'line',
    use: {
        baseURL: 'http://127.0.0.1:4182',
        browserName: 'chromium',
        viewport: { width: 1280, height: 900 },
        locale: 'en-US',
        timezoneId: 'UTC',
        colorScheme: 'light',
        reducedMotion: 'reduce',
        serviceWorkers: 'block',
        trace: 'off',
        screenshot: 'off',
        video: 'off',
        actionTimeout: 8_000,
        navigationTimeout: 15_000,
    },
    webServer: {
        cwd: root,
        command: 'php tests/FinancialE2EServer.php',
        url: 'http://127.0.0.1:4182/login',
        reuseExistingServer: false,
        timeout: 30_000,
        stdout: 'ignore',
        stderr: 'pipe',
    },
});
