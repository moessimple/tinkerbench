import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { ExceptionFrame, FeedItem } from '@/types';
import ExceptionCard from './ExceptionCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves ExceptionCard's content.
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

function exception(
    frames: ExceptionFrame[],
): Extract<FeedItem, { kind: 'exception' }> {
    return {
        frames,
        kind: 'exception',
        line: 4,
        message: 'nope',
        type: 'RuntimeException',
    };
}

it('shows the type and message on the danger variant', () => {
    const { container } = render(ExceptionCard, {
        props: { entry: exception([]) },
    });

    const card = container.querySelector('[data-label="Exception"]');
    expect(card?.getAttribute('data-variant')).toBe('danger');
    expect(card?.textContent).toContain('RuntimeException');
    expect(card?.textContent).toContain('nope');
});

it('hands Card a copy string with a titled header and numbered trace', () => {
    const { container } = render(ExceptionCard, {
        props: {
            entry: exception([
                {
                    file: '/app/Foo.php',
                    function: 'handle',
                    line: 10,
                    snippet: false,
                    vendor: false,
                },
                {
                    file: '/tmp/snippet.php',
                    function: null,
                    line: 3,
                    snippet: true,
                    vendor: false,
                },
            ]),
        },
    });

    expect(
        container
            .querySelector('[data-label="Exception"]')
            ?.getAttribute('data-copy'),
    ).toBe(
        '# Exception - RuntimeException\n\nnope\n\n## Stack Trace\n\n0 - /app/Foo.php:10\n1 - snippet:3',
    );
});

it('omits the stack-trace section from the copy string when there are no frames', () => {
    const { container } = render(ExceptionCard, {
        props: { entry: exception([]) },
    });

    expect(
        container
            .querySelector('[data-label="Exception"]')
            ?.getAttribute('data-copy'),
    ).toBe('# Exception - RuntimeException\n\nnope');
});

it('renders the trace disclosure with de-emphasised vendor frames', () => {
    const { container } = render(ExceptionCard, {
        props: {
            entry: exception([
                {
                    file: '/app/Foo.php',
                    function: 'handle',
                    line: 10,
                    snippet: false,
                    vendor: false,
                },
                {
                    file: '/vendor/laravel/x.php',
                    function: 'run',
                    line: 99,
                    snippet: false,
                    vendor: true,
                },
            ]),
        },
    });

    expect(container.querySelector('summary')?.textContent?.trim()).toBe(
        '2 stack frames',
    );
    expect(container.querySelector('[data-vendor="true"]')).not.toBeNull();
    expect(container.textContent).toContain('/vendor/laravel/x.php:99');
    expect(container.textContent).toContain('· run');
});

it('labels a lone non-snippet frame with a singular noun', () => {
    const { container } = render(ExceptionCard, {
        props: {
            entry: exception([
                {
                    file: '/vendor/x.php',
                    function: 'run',
                    line: 1,
                    snippet: false,
                    vendor: true,
                },
            ]),
        },
    });

    expect(container.querySelector('summary')?.textContent?.trim()).toBe(
        '1 stack frame',
    );
});

it('omits the disclosure when the only frame is the snippet itself', () => {
    const { container } = render(ExceptionCard, {
        props: {
            entry: exception([
                {
                    file: '/tmp/snippet.php',
                    function: null,
                    line: 3,
                    snippet: true,
                    vendor: false,
                },
            ]),
        },
    });

    expect(container.querySelector('details')).toBeNull();
});

it('shows a snippet frame as "snippet:line" rather than its temp path', () => {
    const { container } = render(ExceptionCard, {
        props: {
            entry: exception([
                {
                    file: '/private/var/tmp/tinkerbench-snippet-abc.php',
                    function: null,
                    line: 8,
                    snippet: true,
                    vendor: false,
                },
                {
                    file: '/vendor/x.php',
                    function: 'run',
                    line: 1,
                    snippet: false,
                    vendor: true,
                },
            ]),
        },
    });

    const trace = container.querySelector('details');
    expect(trace?.textContent).toContain('snippet:8');
    expect(trace?.textContent).not.toContain('tinkerbench-snippet-abc');
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(ExceptionCard, {
        props: { entry: exception([]) },
    });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[4]]);
});
