import { ref, watchEffect } from 'vue';

export type Theme = 'light' | 'dark';

function readInitialTheme(): Theme {
    const stored = localStorage.getItem('theme');

    if (stored === 'light' || stored === 'dark') {
        return stored;
    }

    return window.matchMedia('(prefers-color-scheme: dark)').matches
        ? 'dark'
        : 'light';
}

const theme = ref<Theme>(readInitialTheme());

watchEffect(
    () => {
        document.documentElement.classList.toggle(
            'dark',
            theme.value === 'dark',
        );
        localStorage.setItem('theme', theme.value);
    },
    { flush: 'sync' },
);

export function useTheme() {
    function toggleTheme() {
        theme.value = theme.value === 'dark' ? 'light' : 'dark';
    }

    return { theme, toggleTheme };
}
