import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { FeedEntry } from '@/lib/feed';
import { executeScripts } from '@/lib/output';
import OutputFeed from './OutputFeed.vue';

// output.ts has its own test (output.test.ts); executeScripts touches the real DOM, so it is
// stubbed here to assert it runs, while detectOutput/highlightJson stay real (leaf, pure).
vi.mock('@/lib/output', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    executeScripts: vi.fn(),
}));

// Card has its own test (Card.test.ts); stubbed to a shell that exposes its props and a hook to
// fire navigate, so this test only proves OutputFeed's per-kind content and event wiring.
vi.mock('./Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant">
            <slot /><slot name="footer" />
            <button class="stub-nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

function renderFeed(items: FeedEntry[]) {
    return render(OutputFeed, { props: { items } });
}

it('renders one card per feed entry', () => {
    renderFeed([
        { html: '<i>x</i>', kind: 'dump', line: 1 },
        { context: null, kind: 'log', label: 'info', line: 2, message: 'hi' },
    ]);

    expect(screen.getAllByRole('article')).toHaveLength(2);
});

it('renders a dump entry as its html under a Dump card', () => {
    const { container } = renderFeed([
        { html: '<i>dumped</i>', kind: 'dump', line: 1 },
    ]);

    const card = container.querySelector('[data-label="Dump"]');
    expect(card?.innerHTML).toContain('<i>dumped</i>');
});

it('flags a slow, duplicated query', () => {
    const { container } = renderFeed([
        {
            connection: 'sqlite',
            duplicate: true,
            duration_str: '120.00ms',
            kind: 'query',
            line: 3,
            slow: true,
            sql: 'select * from users',
        },
    ]);

    const card = container.querySelector('[data-label="Query"]');
    expect(card?.textContent).toContain('select * from users');
    expect(card?.textContent?.toLowerCase()).toContain('slow');
    expect(card?.textContent?.toLowerCase()).toContain('duplicate');
});

it('drops query cards but keeps everything else when hideQueries is set', () => {
    const { container } = render(OutputFeed, {
        props: {
            hideQueries: true,
            items: [
                {
                    connection: 'sqlite',
                    duplicate: false,
                    duration_str: '1.00ms',
                    kind: 'query',
                    line: 1,
                    slow: false,
                    sql: 'select 1',
                },
                { html: '<i>x</i>', kind: 'dump', line: 2 },
            ] as FeedEntry[],
        },
    });

    expect(container.querySelector('[data-label="Query"]')).toBeNull();
    expect(container.querySelector('[data-label="Dump"]')).not.toBeNull();
});

it('gives a severe log entry the danger variant and a routine one the default', () => {
    const { container } = renderFeed([
        {
            context: null,
            kind: 'log',
            label: 'error',
            line: 1,
            message: 'boom',
        },
        {
            context: null,
            kind: 'log',
            label: 'info',
            line: 2,
            message: 'noted',
        },
    ]);

    const cards = container.querySelectorAll('[data-label="Log"]');
    expect(cards[0].getAttribute('data-variant')).toBe('danger');
    expect(cards[1].getAttribute('data-variant')).toBe('default');
});

it('renders an exception with its type, message and de-emphasised vendor frames', () => {
    const { container } = renderFeed([
        {
            frames: [
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
            ],
            kind: 'exception',
            line: 4,
            message: 'nope',
            type: 'RuntimeException',
        },
    ]);

    const card = container.querySelector('[data-label="Exception"]');
    expect(card?.getAttribute('data-variant')).toBe('danger');
    expect(card?.textContent).toContain('RuntimeException');
    expect(card?.textContent).toContain('nope');
    expect(card?.querySelector('details')).not.toBeNull();
    expect(card?.querySelector('summary')?.textContent?.trim()).toBe(
        '2 stack frames',
    );
    expect(card?.querySelector('[data-vendor="true"]')).not.toBeNull();
});

it('labels the stack trace disclosure with a singular noun for a lone frame', () => {
    const { container } = renderFeed([
        {
            frames: [
                {
                    file: '/vendor/x.php',
                    function: 'run',
                    line: 1,
                    snippet: false,
                    vendor: true,
                },
            ],
            kind: 'exception',
            line: 1,
            message: 'x',
            type: 'RuntimeException',
        },
    ]);

    const summary = container.querySelector('[data-label="Exception"] summary');
    expect(summary?.textContent?.trim()).toBe('1 stack frame');
});

it('omits the stack trace disclosure when the only frame is the snippet itself', () => {
    const { container } = renderFeed([
        {
            frames: [
                {
                    file: '/tmp/snippet.php',
                    function: null,
                    line: 3,
                    snippet: true,
                    vendor: false,
                },
            ],
            kind: 'exception',
            line: 3,
            message: 'boom',
            type: 'RuntimeException',
        },
    ]);

    const card = container.querySelector('[data-label="Exception"]');
    expect(card?.textContent).toContain('boom');
    expect(card?.querySelector('details')).toBeNull();
});

it('shows a snippet frame as "snippet:line" rather than its temp path', () => {
    const { container } = renderFeed([
        {
            frames: [
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
            ],
            kind: 'exception',
            line: 8,
            message: 'x',
            type: 'RuntimeException',
        },
    ]);

    const trace = container.querySelector('[data-label="Exception"] details');
    expect(trace?.textContent).toContain('snippet:8');
    expect(trace?.textContent).not.toContain('tinkerbench-snippet-abc');
});

it('renders the raw stdout Output entry as text', () => {
    const { container } = renderFeed([
        { kind: 'output', text: 'plain printed line' },
    ]);

    const card = container.querySelector('[data-label="Output"]');
    expect(card?.textContent).toContain('plain printed line');
});

it('runs executeScripts when the items change', async () => {
    const { rerender } = renderFeed([
        { html: '<i>a</i>', kind: 'dump', line: 1 },
    ]);

    vi.mocked(executeScripts).mockClear();

    await rerender({ items: [{ html: '<i>b</i>', kind: 'dump', line: 2 }] });

    expect(executeScripts).toHaveBeenCalled();
});

it('re-emits navigate from a child card', async () => {
    const { emitted, container } = renderFeed([
        { html: '<i>x</i>', kind: 'dump', line: 7 },
    ]);

    await fireEvent.click(container.querySelector('.stub-nav') as HTMLElement);

    expect(emitted().navigate).toEqual([[7]]);
});
