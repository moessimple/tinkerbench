import { afterEach, beforeEach, expect, it, vi } from 'vitest';

function stubMatchMedia(matchesDark: boolean) {
    vi.stubGlobal(
        'matchMedia',
        vi.fn().mockImplementation((query: string) => ({
            matches: query === '(prefers-color-scheme: dark)' && matchesDark,
        })),
    );
}

beforeEach(() => {
    localStorage.clear();
    document.documentElement.classList.remove('dark');
});

afterEach(() => {
    vi.unstubAllGlobals();
    vi.resetModules();
});

it('falls back to the light system preference when nothing is stored', async () => {
    stubMatchMedia(false);
    const { useTheme } = await import('./useTheme');

    const { theme } = useTheme();

    expect(theme.value).toBe('light');
    expect(document.documentElement.classList.contains('dark')).toBe(false);
});

it('falls back to the dark system preference when nothing is stored', async () => {
    stubMatchMedia(true);
    const { useTheme } = await import('./useTheme');

    const { theme } = useTheme();

    expect(theme.value).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
});

it('prefers a stored choice over the system preference', async () => {
    stubMatchMedia(true);
    localStorage.setItem('theme', 'light');
    const { useTheme } = await import('./useTheme');

    const { theme } = useTheme();

    expect(theme.value).toBe('light');
});

it('flips the theme, persists it, and toggles the dark class', async () => {
    stubMatchMedia(false);
    const { useTheme } = await import('./useTheme');

    const { theme, toggleTheme } = useTheme();
    toggleTheme();

    expect(theme.value).toBe('dark');
    expect(localStorage.getItem('theme')).toBe('dark');
    expect(document.documentElement.classList.contains('dark')).toBe(true);
});
