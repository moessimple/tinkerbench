import type { Auth } from '@/types/auth';

// Extend ImportMeta interface for Vite...
declare module 'vite/client' {
    interface ImportMetaEnv {
        readonly VITE_APP_NAME: string;
        [key: string]: string | boolean | undefined;
    }

    interface ImportMeta {
        readonly env: ImportMetaEnv;
        readonly glob: <T>(pattern: string) => Record<string, () => Promise<T>>;
    }
}

declare module '@inertiajs/core' {
    export interface InertiaConfig {
        sharedPageProps: {
            name: string;
            auth: Auth;
            sidebarOpen: boolean;
            [key: string]: unknown;
        };
    }
}

declare module 'vue' {
    interface ComponentCustomProperties {
        $inertia: typeof Router;
        $page: Page;
        $headManager: ReturnType<typeof createHeadManager>;
    }
}

declare global {
    interface MonacoEditorInstance {
        dispose(): void;
        focus(): void;
        getValue(): string;
        layout(): void;
        onDidChangeModelContent(callback: () => void): void;
    }

    interface MonacoApi {
        editor: {
            create(
                element: HTMLElement,
                options: Record<string, unknown>,
            ): MonacoEditorInstance;
            defineTheme(name: string, theme: Record<string, unknown>): void;
        };
    }

    interface AmdRequire {
        (modules: string[], callback: () => void): void;
        config(options: { paths: { vs: string } }): void;
    }

    interface Window {
        monaco: MonacoApi;
        require: AmdRequire;
    }
}
