import { fireEvent, render } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { FeedEntry } from '@/lib/feed';
import { executeScripts } from '@/lib/output';
import OutputFeed from './OutputFeed.vue';

// output.ts has its own test (output.test.ts); executeScripts touches the real DOM, so it is
// stubbed here to assert it runs.
vi.mock('@/lib/output', async (importOriginal) => ({
    ...(await importOriginal<Record<string, unknown>>()),
    executeScripts: vi.fn(),
}));

// Each kind card has its own test under feed/; here they are stubbed to a shell that echoes the
// entry kind, its query sql (for sort assertions) and a navigate hook, so this test only proves
// OutputFeed's dispatch, filter, sort, empty state and navigate re-emit.
const { kindStub } = vi.hoisted(() => ({
    kindStub: (kind: string) => ({
        props: ['entry'],
        emits: ['navigate'],
        template: `<article data-kind="${kind}">
            <span class="sql">{{ entry.sql }}</span>
            <button class="nav" @click="$emit('navigate', entry.line)">nav</button>
        </article>`,
    }),
}));

vi.mock('./feed/DumpCard.vue', () => ({ default: kindStub('dump') }));
vi.mock('./feed/QueryCard.vue', () => ({ default: kindStub('query') }));
vi.mock('./feed/LogCard.vue', () => ({ default: kindStub('log') }));
vi.mock('./feed/ExceptionCard.vue', () => ({ default: kindStub('exception') }));
vi.mock('./feed/NPlusOneCard.vue', () => ({ default: kindStub('n_plus_one') }));
vi.mock('./feed/ResultCard.vue', () => ({ default: kindStub('result') }));
vi.mock('./feed/OutputCard.vue', () => ({ default: kindStub('output') }));

const dump = (line: number): FeedEntry => ({
    html: '<i>x</i>',
    kind: 'dump',
    line,
});
const query = (sql: string, ms: number): FeedEntry => ({
    connection: 'sqlite',
    duplicate: false,
    duration_ms: ms,
    duration_str: `${ms}.00ms`,
    kind: 'query',
    line: null,
    slow: false,
    sql,
});

function renderFeed(items: FeedEntry[], props: Record<string, unknown> = {}) {
    return render(OutputFeed, { props: { items, ...props } });
}

it('renders one card per entry, dispatched to the component for its kind', () => {
    const { container } = renderFeed([
        dump(1),
        { context: null, kind: 'log', label: 'info', line: 2, message: 'hi' },
        { kind: 'exception', line: 3, message: 'boom', type: 'E', frames: [] },
        {
            count: 4,
            kind: 'n_plus_one',
            line: 5,
            model: 'App\\Models\\User',
            relation: 'posts',
        },
        { html: '<i>v</i>', kind: 'result' },
    ]);

    const kinds = [...container.querySelectorAll('[data-kind]')].map((el) =>
        el.getAttribute('data-kind'),
    );
    expect(kinds).toEqual(['dump', 'log', 'exception', 'n_plus_one', 'result']);
});

it('narrows the feed to entries of the selected kind when a filter is set', () => {
    const { container } = renderFeed([query('select 1', 1), dump(2)], {
        filter: 'query',
    });

    expect(container.querySelector('[data-kind="query"]')).not.toBeNull();
    expect(container.querySelector('[data-kind="dump"]')).toBeNull();
});

it('orders query entries slowest first when the slowest sort is set', () => {
    const { container } = renderFeed(
        [query('select 1', 1), query('select 2', 50), query('select 3', 5)],
        { filter: 'query', sort: 'slowest' },
    );

    const order = [...container.querySelectorAll('.sql')].map(
        (el) => el.textContent,
    );
    expect(order).toEqual(['select 2', 'select 3', 'select 1']);
});

it('keeps execution order under the recent sort', () => {
    const { container } = renderFeed(
        [query('select 1', 50), query('select 2', 1)],
        { filter: 'query', sort: 'recent' },
    );

    const order = [...container.querySelectorAll('.sql')].map(
        (el) => el.textContent,
    );
    expect(order).toEqual(['select 1', 'select 2']);
});

it('shows a facet-specific empty message when a filter matches nothing', () => {
    const { container } = renderFeed([dump(1)], { filter: 'exception' });

    expect(container.querySelector('[data-kind]')).toBeNull();
    expect(container.textContent).toContain('No exceptions on this run');
});

it('shows no empty message on the all facet', () => {
    const { container } = renderFeed([], { filter: 'all' });

    expect(container.textContent?.trim()).toBe('');
});

it('runs executeScripts when the items change', async () => {
    const { rerender } = renderFeed([dump(1)]);

    vi.mocked(executeScripts).mockClear();

    await rerender({ items: [dump(2)] });

    expect(executeScripts).toHaveBeenCalled();
});

it('re-emits navigate from a child card', async () => {
    const { emitted, container } = renderFeed([dump(7)]);

    await fireEvent.click(container.querySelector('.nav') as HTMLElement);

    expect(emitted().navigate).toEqual([[7]]);
});
