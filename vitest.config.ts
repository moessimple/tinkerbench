import vue from '@vitejs/plugin-vue';
import path from 'node:path';
import { configDefaults, defineConfig } from 'vitest/config';

// Only the Vue SFC plugin is reused from vite.config.ts here. The other plugins
// there (laravel-vite-plugin, wayfinder, inertia) target a real dev/build server
// and either need a running artisan process or a Laravel manifest, neither of
// which exists under Vitest.
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
        // pest-plugin-browser ships a Playwright example spec under vendor/; Vitest's
        // default exclude list does not cover vendor/, so it would try to run it.
        exclude: [...configDefaults.exclude, 'vendor/**'],
        coverage: {
            // Enforced only under `--coverage` (the `test:unit` script), mirroring the PHP
            // side's `pest --coverage --exactly=100.0`: every runtime module ships with a
            // test that covers it.
            provider: 'v8',
            // Terminal-only, like the PHP side; no report directory is written.
            reporter: ['text'],
            include: ['resources/js/**/*.{ts,vue}'],
            exclude: [
                'resources/js/**/*.test.ts',
                'resources/js/types/**',
                // Wayfinder-generated route/controller helpers.
                'resources/js/actions/**',
                'resources/js/routes/**',
                'resources/js/wayfinder/**',
                // Inertia bootstrap entry point, the JS counterpart of bootstrap/app.php,
                // which the PHP <source> set also leaves out.
                'resources/js/app.ts',
                // Constructs a real Web Worker via Vite's `?worker` import, which jsdom
                // cannot instantiate; mocked wherever it is exercised (MonacoEditor.test.ts).
                'resources/js/lib/monacoEditorWorker.ts',
            ],
            // Line coverage only, matching `pest --coverage --exactly=100.0` on the PHP side.
            thresholds: { lines: 100 },
        },
    },
});
