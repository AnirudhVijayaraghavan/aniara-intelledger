import tailwindcss from '@tailwindcss/vite';
import { fileURLToPath } from 'node:url';
import { build, preview } from 'vite';

const root = fileURLToPath(new URL('../../', import.meta.url));
const fixtureRoot = fileURLToPath(
    new URL('../Browser/fixture/', import.meta.url),
);
const noBackend = fileURLToPath(
    new URL('../Browser/fixture/no-backend.ts', import.meta.url),
);
const configuration = {
    configFile: false,
    envDir: false,
    root: fixtureRoot,
    publicDir: false,
    logLevel: 'warn',
    resolve: {
        alias: [
            { find: '@/routes/categories', replacement: noBackend },
            { find: '@/routes/payees', replacement: noBackend },
            { find: '@', replacement: `${root}resources/js` },
        ],
    },
    plugins: [tailwindcss()],
    build: {
        outDir: `${root}.phpunit.cache/browser-harness`,
        emptyOutDir: true,
    },
    preview: { host: '127.0.0.1', port: 4178, strictPort: true },
};

// Deliberately independent of the application Vite config, .env, PHP, and Wayfinder.
await build(configuration);
const server = await preview(configuration);
console.log('Synthetic component harness listening on http://127.0.0.1:4178');
for (const signal of ['SIGINT', 'SIGTERM']) {
    process.once(signal, () => server.httpServer.close());
}
