import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import LogCard from './LogCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves LogCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant', 'copy'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant" :data-copy="copy">
            <slot />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

function log(overrides: Record<string, unknown> = {}) {
    return {
        context_html: null,
        context_text: null,
        kind: 'log' as const,
        label: 'info',
        line: 2,
        message: 'cache warmed',
        ...overrides,
    };
}

it('shows the level and message for a routine log on the default variant', () => {
    const { container } = render(LogCard, { props: { entry: log() } });

    const card = container.querySelector('[data-label="Log"]');
    expect(card?.getAttribute('data-variant')).toBe('default');
    expect(card?.textContent).toContain('info');
    expect(card?.textContent).toContain('cache warmed');
});

it('marks a severe level danger', () => {
    const { container } = render(LogCard, {
        props: { entry: log({ label: 'error', message: 'boom' }) },
    });

    expect(
        container
            .querySelector('[data-label="Log"]')
            ?.getAttribute('data-variant'),
    ).toBe('danger');
});

it('renders the context dump tree when present', () => {
    const { container } = render(LogCard, {
        props: {
            entry: log({
                context_html: '<span class="ctx">array:1</span>',
                context_text: 'array:1 [ …',
            }),
        },
    });

    expect(container.querySelector('.overflow-x-auto .ctx')?.textContent).toBe(
        'array:1',
    );
});

it('omits the context block when there is none', () => {
    const { container } = render(LogCard, { props: { entry: log() } });

    expect(container.querySelector('.overflow-x-auto')).toBeNull();
});

it('hands Card a copy string of the message and the context text', () => {
    const { container } = render(LogCard, {
        props: {
            entry: log({
                message: 'ctx',
                context_text: 'array:1 [ "user" => 1 ]',
            }),
        },
    });

    expect(
        container
            .querySelector('[data-label="Log"]')
            ?.getAttribute('data-copy'),
    ).toBe('ctx\narray:1 [ "user" => 1 ]');
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(LogCard, {
        props: { entry: log({ line: 8, message: 'x' }) },
    });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[8]]);
});
