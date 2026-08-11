import { render, screen } from '@testing-library/vue';
import { describe, expect, test, vi } from 'vitest';

// <Head> reads a headManager singleton that only exists once createInertiaApp() has
// run, which never happens in a unit test; replace it with a passthrough so this test
// can focus on the page body.
vi.mock('@inertiajs/vue3', () => ({
    Head: {
        template: '<div><slot /></div>',
    },
}));

const { default: Welcome } = await import('./Welcome.vue');

describe('Welcome', () => {
    test('renders the getting started heading and documentation link', () => {
        render(Welcome);

        expect(
            screen.getByRole('heading', { name: "Let's get started" }),
        ).toBeTruthy();
        expect(
            screen
                .getByRole('link', { name: /documentation/i })
                .getAttribute('href'),
        ).toBe('https://laravel.com/docs');
    });
});
