import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import LogCard from './LogCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves LogCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant">
            <slot />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

it('shows the level and message for a routine log on the default variant', () => {
    const { container } = render(LogCard, {
        props: {
            entry: {
                context: null,
                kind: 'log',
                label: 'info',
                line: 2,
                message: 'cache warmed',
            },
        },
    });

    const card = container.querySelector('[data-label="Log"]');
    expect(card?.getAttribute('data-variant')).toBe('default');
    expect(card?.textContent).toContain('info');
    expect(card?.textContent).toContain('cache warmed');
});

it('marks a severe level danger', () => {
    const { container } = render(LogCard, {
        props: {
            entry: {
                context: null,
                kind: 'log',
                label: 'error',
                line: 1,
                message: 'boom',
            },
        },
    });

    expect(
        container
            .querySelector('[data-label="Log"]')
            ?.getAttribute('data-variant'),
    ).toBe('danger');
});

it('renders the encoded context when present', () => {
    const { container } = render(LogCard, {
        props: {
            entry: {
                context: '{"user":1}',
                kind: 'log',
                label: 'debug',
                line: 3,
                message: 'ctx',
            },
        },
    });

    expect(container.querySelector('pre')?.textContent).toBe('{"user":1}');
});

it('omits the context block when there is none', () => {
    const { container } = render(LogCard, {
        props: {
            entry: {
                context: null,
                kind: 'log',
                label: 'info',
                line: 3,
                message: 'no ctx',
            },
        },
    });

    expect(container.querySelector('pre')).toBeNull();
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(LogCard, {
        props: {
            entry: {
                context: null,
                kind: 'log',
                label: 'info',
                line: 8,
                message: 'x',
            },
        },
    });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[8]]);
});
