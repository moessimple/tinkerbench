import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import ViewCard from './ViewCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves ViewCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant', 'copy'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-copy="copy">
            <slot />
            <slot name="footer" />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

it('renders the view path and rendered data under a View card at its line', () => {
    const { container } = render(ViewCard, {
        props: {
            entry: {
                data_html: '<i>x: 1</i>',
                data_text: 'x: 1',
                kind: 'view',
                line: 5,
                path: '/app/resources/views/welcome.blade.php',
            },
        },
    });

    const card = container.querySelector('[data-label="View"]');
    expect(card?.getAttribute('data-line')).toBe('5');
    expect(card?.innerHTML).toContain('<i>x: 1</i>');
    expect(card?.textContent).toContain(
        '/app/resources/views/welcome.blade.php',
    );
});

it('hands Card the plain-text form of the view data for copying', () => {
    const { container } = render(ViewCard, {
        props: {
            entry: {
                data_html: '<i>x</i>',
                data_text: 'x: 1',
                kind: 'view',
                line: 5,
                path: '/x.blade.php',
            },
        },
    });

    expect(
        container
            .querySelector('[data-label="View"]')
            ?.getAttribute('data-copy'),
    ).toBe('x: 1');
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(ViewCard, {
        props: {
            entry: {
                data_html: '<i>x</i>',
                data_text: 'x',
                kind: 'view',
                line: 9,
                path: '/x.blade.php',
            },
        },
    });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[9]]);
});
