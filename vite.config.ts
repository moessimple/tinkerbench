import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import vue from '@vitejs/plugin-vue';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig, lazyPlugins } from 'vite-plus';

export default defineConfig({
    lint: {
        options: {
            typeAware: true,
            typeCheck: true,
        },
        plugins: ['eslint', 'typescript', 'unicorn', 'oxc', 'vue'],
        ignorePatterns: [
            'vite.config.ts',
            'vitest.config.ts',
            'vitest.setup.ts',
            'resources/js/actions/*',
            'resources/js/routes/*',
            'resources/js/wayfinder/*',
            'resources/js/components/ui/*',
        ],
    },
    fmt: {
        printWidth: 80,
        tabWidth: 4,
        useTabs: false,
        semi: true,
        singleQuote: true,
        overrides: [
            {
                files: ['**/*.yml'],
                options: {
                    tabWidth: 2,
                },
            },
        ],
        sortTailwindcss: {
            functions: ['clsx', 'cn', 'cva'],
            stylesheet: 'resources/css/app.css',
        },
        sortImports: {
            groups: [
                'builtin',
                'external',
                'internal',
                'parent',
                'sibling',
                'index',
            ],
            newlinesBetween: false,
        },
        ignorePatterns: [
            'resources/js/actions/*',
            'resources/js/routes/*',
            'resources/js/wayfinder/*',
            'resources/js/components/ui/*',
            'resources/views/mail/*',
        ],
    },
    plugins: lazyPlugins(() => [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.ts'],
            refresh: true,
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                bunny('Fira Code', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        tailwindcss(),
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
        wayfinder({
            formVariants: true,
        }),
    ]),
    server: {
        watch: {
            ignored: [
                '**/.agents/**',
                '**/.claude/**',
                '**/.cursor/**',
                '**/.junie/**',
                '**/vendor/**',
            ],
        },
    },
});
