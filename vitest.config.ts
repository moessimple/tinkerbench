import vue from '@vitejs/plugin-vue';
import path from 'node:path';
import { configDefaults, defineConfig } from 'vitest/config';

// Only the Vue SFC plugin is reused from vite.config.ts. The other plugins there
// (laravel-vite-plugin, wayfinder, inertia) target a real dev/build server and
// need a running artisan process or a Laravel manifest, neither of which exists
// under Vitest.
export default defineConfig({
    plugins: [vue()],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, './resources/js'),
        },
    },
    test: {
        environment: 'jsdom',
        setupFiles: ['./vitest.setup.ts'],
        // Vitest's default exclude list does not cover vendor/; pest-plugin-browser
        // ships a Playwright spec there that would otherwise be collected.
        exclude: [...configDefaults.exclude, 'vendor/**'],
        coverage: {
            // Line coverage only, as a single total across all files, matching the
            // PHP side's pest --coverage --exactly=100.0 (percentageOfExecutedLines
            // over app/). Branches, functions and statements are not gated.
            provider: 'v8',
            reporter: ['text'],
            include: ['resources/js/**/*.ts', 'resources/js/**/*.vue'],
            exclude: [
                'resources/js/**/*.test.ts',
                'resources/js/types/**',
                // Wayfinder-generated route/controller helpers.
                'resources/js/actions/**',
                'resources/js/routes/**',
                'resources/js/wayfinder/**',
                // Inertia bootstrap entry point, the JS counterpart of
                // bootstrap/app.php, which the PHP <source> set also leaves out.
                'resources/js/app.ts',
                // Constructs a real Web Worker via Vite's `?worker` import, which
                // jsdom cannot instantiate; mocked wherever it is exercised
                // (MonacoEditor.test.ts).
                'resources/js/lib/monacoEditorWorker.ts',
            ],
            thresholds: { lines: 100 },
        },
    },
});
