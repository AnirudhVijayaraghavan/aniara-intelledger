import { randomUUID } from 'node:crypto';
import { mkdir, rmdir, unlink, writeFile } from 'node:fs/promises';
import { join } from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import { build } from 'vite';

const root = fileURLToPath(new URL('../../', import.meta.url));

/** Render the actual TSX components with the installed toolchain, without PHP or a DOM shim. */
export async function loadReactComponents() {
    const id = '\0accessibility-components';
    const result = await build({
        configFile: false,
        envFile: false,
        logLevel: 'silent',
        resolve: { alias: { '@': join(root, 'resources/js') } },
        plugins: [
            {
                name: 'accessibility-component-contracts',
                resolveId: (source) =>
                    source === 'virtual:accessibility-components' ? id : null,
                load: (source) =>
                    source === id
                        ? `
                    export { default as PasswordInput } from '@/components/password-input';
                    export { TransactionFinancialFields, TransactionMetadataFields } from '@/components/transaction-fields';
                    export { FinancialChoice } from '@/components/financial-choice';
                    export { Button } from '@/components/ui/button';
                    export { Badge } from '@/components/ui/badge';
                    export { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
                `
                        : null,
            },
        ],
        build: {
            ssr: true,
            rolldownOptions: { input: 'virtual:accessibility-components' },
            write: false,
            minify: false,
        },
    });
    const output = Array.isArray(result) ? result[0].output : result.output;
    if (output.length !== 1 || output[0].type !== 'chunk') {
        throw new Error('Expected a single isolated component test module.');
    }
    const directory = join(
        root,
        '.phpunit.cache',
        `react-components-${randomUUID()}`,
    );
    await mkdir(directory, { recursive: true });
    const path = join(directory, 'components.mjs');
    const cleanup = async () => {
        await unlink(path);
        await rmdir(directory);
    };
    await writeFile(path, output[0].code, { flag: 'wx' });
    try {
        return { components: await import(pathToFileURL(path).href), cleanup };
    } catch (error) {
        await cleanup();
        throw error;
    }
}
