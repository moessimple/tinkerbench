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
    },
});
