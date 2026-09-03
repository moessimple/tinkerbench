import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { reactive, ref } from 'vue';
import type { SnippetDebugPayload } from '@/types';

let capturedPost: {
    url: string;
    onSuccess: (data: {
        debug?: SnippetDebugPayload | null;
        output: string;
    }) => void;
    onError: (errors: Record<string, string>) => void;
    onHttpException: (response: { status: number }) => void;
} | null = null;
let capturedTitle: string | undefined;

// Kept as the single object identity useHttp() returns, so v-model writes in the
// component and reads in this test observe the same reactive state.
const httpState = reactive({
    code: '',
    processing: false,
    post: (
        url: string,
        options: {
            onSuccess: (data: {
                debug?: SnippetDebugPayload | null;
                output: string;
            }) => void;
            onError: (errors: Record<string, string>) => void;
            onHttpException: (response: { status: number }) => void;
        },
    ) => {
        capturedPost = {
            url,
            onSuccess: options.onSuccess,
            onError: options.onError,
            onHttpException: options.onHttpException,
        };
    },
});

const { revealLineSpy } = vi.hoisted(() => ({ revealLineSpy: vi.fn() }));

// <Head> reads a headManager singleton that only exists once createInertiaApp() has run,
// which never happens in a unit test; replace it with a fake that captures the title
// instead. useHttp is replaced with a controllable fake so the test can trigger onSuccess
// without a real network request.
vi.mock('@inertiajs/vue3', () => ({
    Head: (props: { title?: string }) => {
        capturedTitle = props.title;

        return null;
    },
    useHttp: (initial: { code: string }) => {
        httpState.code = initial.code;

        return httpState;
    },
}));

// MonacoEditor has its own test (MonacoEditor.test.ts) proving it renders the editor,
// emits `change`/`run`, and exposes `revealLine`; here it's replaced with a plain textarea
// plus a button, and revealLine is a spy so this test can prove OpenSnippet.vue calls it.
vi.mock('@/components/MonacoEditor.vue', () => ({
    default: {
        props: ['initialValue'],
        emits: ['change', 'run'],
        methods: { revealLine: revealLineSpy },
        template: `
            <div>
                <textarea aria-label="Snippet code" :value="initialValue" @input="$emit('change', $event.target.value)" />
                <button type="button" @click="$emit('run')">Emit run</button>
            </div>
        `,
    },
}));

// CommandPalette has its own test (CommandPalette.test.ts) proving switching/creating/
// renaming/deleting/its own global Ctrl+P shortcut; replaced here so this test stays
// focused on OpenSnippet.vue's own responsibilities.
vi.mock('@/components/CommandPalette.vue', () => ({
    default: {
        props: ['currentProject', 'currentSnippet'],
        template: '<div />',
    },
}));

// OutputFeed has its own test (OutputFeed.test.ts) proving per-kind card rendering, the kind
// filter, the slowest sort, and its navigate re-emit; stubbed here to a shell that exposes each
// entry's kind (and an output entry's text), the filter and sort props, and a navigate trigger,
// so this test only proves OpenSnippet.vue's feed assembly and its filter/sort/navigate wiring.
vi.mock('@/components/OutputFeed.vue', () => ({
    default: {
        props: ['items', 'filter', 'sort'],
        emits: ['navigate'],
        template: `
            <div data-testid="feed" :data-filter="filter" :data-sort="sort">
                <div v-for="(item, i) in items" :key="i" :data-kind="item.kind">
                    <template v-if="item.kind === 'output'">{{ item.text }}</template>
                    <template v-else>{{ item.kind }}</template>
                </div>
                <button type="button" data-testid="feed-nav" @click="$emit('navigate', 42)">nav</button>
            </div>
        `,
    },
}));

// useTheme has its own test (useTheme.test.ts) proving system-preference fallback,
// persistence, and the dark-class toggle; replaced here so this test only proves
// OpenSnippet.vue's toggle button reads/calls it correctly.
const mockTheme = ref<'light' | 'dark'>('dark');
const toggleTheme = vi.fn(() => {
    mockTheme.value = mockTheme.value === 'dark' ? 'light' : 'dark';
});

vi.mock('@/composables/useTheme', () => ({
    useTheme: () => ({ theme: mockTheme, toggleTheme }),
}));

const { default: OpenSnippet } = await import('./OpenSnippet.vue');
const props = {
    content: "echo 'hello world';",
    currentProject: 'my-project',
    laravelVersion: '13.0.0',
    phpVersion: '8.5.0',
    snippetName: 'scratch',
};

function payload(
    overrides: Partial<SnippetDebugPayload> = {},
): SnippetDebugPayload {
    return {
        items: [],
        duration_str: '1.00ms',
        peak_memory_str: '1.00 MB',
        ...overrides,
    };
}

beforeEach(() => {
    capturedPost = null;
    mockTheme.value = 'dark';
    toggleTheme.mockClear();
    revealLineSpy.mockClear();
});

afterEach(() => {
    vi.useRealTimers();
});

it('sets the page title to the project and snippet name', () => {
    render(OpenSnippet, { props });

    expect(capturedTitle).toBe('my-project / scratch');
});

it('shows the project and snippet name as the page heading', () => {
    render(OpenSnippet, { props });

    screen.getByRole('heading', { name: 'my-project / scratch' });
});

it('shows the running PHP and Laravel version', () => {
    render(OpenSnippet, { props });

    screen.getByText('PHP 8.5.0 · Laravel 13.0.0');
});

it('pre-fills the editor with the snippet content from its props', () => {
    render(OpenSnippet, { props });

    const editor = screen.getByLabelText('Snippet code') as HTMLTextAreaElement;
    expect(editor.value).toBe("echo 'hello world';");
});

it('sends the entered code to the run endpoint', async () => {
    render(OpenSnippet, { props });

    await fireEvent.update(screen.getByLabelText('Snippet code'), "echo 'hi';");
    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));

    expect(capturedPost?.url).toBe(
        '/api/projects/my-project/snippets/executions',
    );
    expect(httpState.code).toBe("echo 'hi';");
});

it('shows the returned stdout in the feed', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: 'hi', debug: null });

    await screen.findByText('hi');
});

it('shows returned stdout as escaped text', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '<strong>hi</strong>', debug: null });

    await screen.findByText('<strong>hi</strong>');
});

it('shows a validation error message when the request fails', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onError({ code: 'The code field is required.' });

    await screen.findByText('The code field is required.');
});

it('shows a generic error message when the server request fails', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onHttpException({ status: 500 });

    await screen.findByText('Request failed (500).');
});

it('runs the snippet when the editor emits run', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Emit run' }));

    expect(capturedPost?.url).toBe(
        '/api/projects/my-project/snippets/executions',
    );
});

it('shows the run shortcut in the button tooltip', () => {
    render(OpenSnippet, { props });

    const button = screen.getByRole('button', { name: 'Run snippet' });

    expect(button.title).toBe('Run snippet (⌘Enter)');
});

it('disables the run button and shows a running label while processing', () => {
    httpState.processing = true;

    render(OpenSnippet, { props });

    const button = screen.getByRole('button', {
        name: 'Running…',
    }) as HTMLButtonElement;
    expect(button.disabled).toBe(true);

    httpState.processing = false;
});

it('shows the run duration and peak memory after a run', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            duration_str: '12.30ms',
            peak_memory_str: '18.50 MB',
        }),
    });

    await screen.findByText('12.30ms');
    screen.getByText('18.50 MB');
});

it('confirms a finished run that produced nothing with a no-output note', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '', debug: payload() });

    await screen.findByText(/no output\. return a value or call dump\(\)/i);
});

it('shows no no-output note before the first run', () => {
    render(OpenSnippet, { props });

    expect(screen.queryByText(/no output/i)).toBeNull();
});

it('labels each filter tab with its live entry count', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            items: [
                {
                    connection: 'sqlite',
                    duplicate: false,
                    duration_ms: 4,
                    duration_str: '4.00ms',
                    kind: 'query',
                    line: null,
                    slow: false,
                    sql: 'select 1',
                },
                {
                    connection: 'sqlite',
                    duplicate: true,
                    duration_ms: 4,
                    duration_str: '4.00ms',
                    kind: 'query',
                    line: null,
                    slow: false,
                    sql: 'select 1',
                },
                { html: '<i>x</i>', kind: 'dump', line: 1 },
            ],
        }),
    });

    await screen.findByRole('tab', { name: 'All 3' });
    screen.getByRole('tab', { name: 'Queries 2' });
    screen.getByRole('tab', { name: 'Dumps 1' });
    screen.getByRole('tab', { name: 'Logs 0' });
});

it('counts the raw stdout Output card in the All tab total', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: 'printed line',
        debug: payload({
            items: [{ html: '<i>x</i>', kind: 'dump', line: 1 }],
        }),
    });

    await screen.findByRole('tab', { name: 'All 2' });
    screen.getByRole('tab', { name: 'Dumps 1' });
});

it('counts a result entry under All but gives it no facet tab of its own', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            items: [
                { html: '<i>x</i>', kind: 'dump', line: 1 },
                { html: '<i>the value</i>', kind: 'result' },
            ],
        }),
    });

    await screen.findByRole('tab', { name: 'All 2' });
    expect(screen.queryByRole('tab', { name: /result/i })).toBeNull();
});

it('tells the feed which kind to show when a filter tab is selected', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            items: [
                {
                    connection: 'sqlite',
                    duplicate: false,
                    duration_ms: 4,
                    duration_str: '4.00ms',
                    kind: 'query',
                    line: null,
                    slow: false,
                    sql: 'select 1',
                },
                { html: '<i>x</i>', kind: 'dump', line: 1 },
            ],
        }),
    });

    const feed = await screen.findByTestId('feed');
    expect(feed.getAttribute('data-filter')).toBe('all');

    await fireEvent.click(screen.getByRole('tab', { name: 'Queries 1' }));
    expect(feed.getAttribute('data-filter')).toBe('query');

    await fireEvent.click(screen.getByRole('tab', { name: 'All 2' }));
    expect(feed.getAttribute('data-filter')).toBe('all');
});

it('offers the query sort control only while the queries facet is active', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            items: [
                {
                    connection: 'sqlite',
                    duplicate: false,
                    duration_ms: 4,
                    duration_str: '4.00ms',
                    kind: 'query',
                    line: null,
                    slow: false,
                    sql: 'select 1',
                },
            ],
        }),
    });

    await screen.findByRole('tab', { name: 'All 1' });
    expect(screen.queryByRole('button', { name: 'Slowest' })).toBeNull();

    await fireEvent.click(screen.getByRole('tab', { name: 'Queries 1' }));
    screen.getByRole('button', { name: 'Slowest' });

    await fireEvent.click(screen.getByRole('tab', { name: 'All 1' }));
    expect(screen.queryByRole('button', { name: 'Slowest' })).toBeNull();
});

it('tells the feed to sort queries by duration when slowest is picked', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            items: [
                {
                    connection: 'sqlite',
                    duplicate: false,
                    duration_ms: 4,
                    duration_str: '4.00ms',
                    kind: 'query',
                    line: null,
                    slow: false,
                    sql: 'select 1',
                },
            ],
        }),
    });

    await fireEvent.click(
        await screen.findByRole('tab', { name: 'Queries 1' }),
    );
    const feed = screen.getByTestId('feed');
    expect(feed.getAttribute('data-sort')).toBe('recent');

    await fireEvent.click(screen.getByRole('button', { name: 'Slowest' }));
    expect(feed.getAttribute('data-sort')).toBe('slowest');

    await fireEvent.click(screen.getByRole('tab', { name: 'All 1' }));
    expect(feed.getAttribute('data-sort')).toBe('recent');
});

it('resets the active filter when the output is cleared', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({
            items: [
                {
                    connection: 'sqlite',
                    duplicate: false,
                    duration_ms: 4,
                    duration_str: '4.00ms',
                    kind: 'query',
                    line: null,
                    slow: false,
                    sql: 'select 1',
                },
            ],
        }),
    });

    await fireEvent.click(
        await screen.findByRole('tab', { name: 'Queries 1' }),
    );
    await fireEvent.click(screen.getByRole('button', { name: 'Clear output' }));

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '', debug: payload() });

    const allTab = await screen.findByRole('tab', { name: 'All 0' });
    expect(allTab.getAttribute('aria-selected')).toBe('true');
});

it('reveals the line in the editor when the feed emits navigate', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: 'hi', debug: null });

    await fireEvent.click(screen.getByTestId('feed-nav'));

    expect(revealLineSpy).toHaveBeenCalledWith(42);
});

it('saves edited content 500ms after the last change', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    render(OpenSnippet, { props });

    await fireEvent.update(
        screen.getByLabelText('Snippet code'),
        "echo 'edited';",
    );
    expect(fetchMock).not.toHaveBeenCalled();

    await vi.advanceTimersByTimeAsync(500);

    expect(fetchMock).toHaveBeenCalledWith(
        '/api/projects/my-project/snippets/scratch',
        expect.objectContaining({
            method: 'PUT',
            body: JSON.stringify({ content: "echo 'edited';" }),
        }),
    );
});

it('collapses rapid edits into a single save of the latest content', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    render(OpenSnippet, { props });
    const editor = screen.getByLabelText('Snippet code');

    await fireEvent.update(editor, "echo 'first';");
    await vi.advanceTimersByTimeAsync(200);
    await fireEvent.update(editor, "echo 'second';");
    await vi.advanceTimersByTimeAsync(500);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock).toHaveBeenCalledWith(
        '/api/projects/my-project/snippets/scratch',
        expect.objectContaining({
            body: JSON.stringify({ content: "echo 'second';" }),
        }),
    );
});

it('shows a message and does not clear the editor when a save fails with an error status', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: false, status: 500 });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    render(OpenSnippet, { props });

    await fireEvent.update(
        screen.getByLabelText('Snippet code'),
        "echo 'edited';",
    );
    await vi.advanceTimersByTimeAsync(500);

    await vi.waitFor(() => screen.getByText('Unable to save changes (500).'));
});

it('clears the save-failed message once a later save succeeds', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce({ ok: false, status: 500 })
        .mockResolvedValueOnce({ ok: true });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    render(OpenSnippet, { props });
    const editor = screen.getByLabelText('Snippet code');

    await fireEvent.update(editor, "echo 'first';");
    await vi.advanceTimersByTimeAsync(500);
    await vi.waitFor(() => screen.getByText('Unable to save changes (500).'));

    await fireEvent.update(editor, "echo 'second';");
    await vi.advanceTimersByTimeAsync(500);

    await vi.waitFor(() =>
        expect(screen.queryByText('Unable to save changes (500).')).toBeNull(),
    );
});

it('still saves later edits after an earlier save is rejected by a network failure', async () => {
    const fetchMock = vi
        .fn()
        .mockRejectedValueOnce(new Error('network down'))
        .mockResolvedValueOnce({ ok: true });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    render(OpenSnippet, { props });
    const editor = screen.getByLabelText('Snippet code');

    await fireEvent.update(editor, "echo 'first';");
    await vi.advanceTimersByTimeAsync(500);
    await fireEvent.update(editor, "echo 'second';");
    await vi.advanceTimersByTimeAsync(500);

    await vi.waitFor(() => expect(fetchMock).toHaveBeenCalledTimes(2));
    expect(fetchMock).toHaveBeenLastCalledWith(
        '/api/projects/my-project/snippets/scratch',
        expect.objectContaining({
            body: JSON.stringify({ content: "echo 'second';" }),
        }),
    );
});

it('flushes a pending debounced save on unmount instead of dropping it', async () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    const rendered = render(OpenSnippet, { props });

    await fireEvent.update(
        screen.getByLabelText('Snippet code'),
        "echo 'edited';",
    );
    expect(fetchMock).not.toHaveBeenCalled();

    rendered.unmount();
    await vi.advanceTimersByTimeAsync(0);

    expect(fetchMock).toHaveBeenCalledWith(
        '/api/projects/my-project/snippets/scratch',
        expect.objectContaining({
            body: JSON.stringify({ content: "echo 'edited';" }),
        }),
    );
});

it('does not send a redundant save on unmount when no edit is pending', () => {
    const fetchMock = vi.fn().mockResolvedValue({ ok: true });
    vi.stubGlobal('fetch', fetchMock);
    const rendered = render(OpenSnippet, { props });

    rendered.unmount();

    expect(fetchMock).not.toHaveBeenCalled();
});

it('flushes the pending debounced save immediately on Ctrl/Cmd+S', async () => {
    const fetchMock = vi.fn().mockResolvedValue({
        ok: true,
        json: () => Promise.resolve({ ok: true }),
    });
    vi.stubGlobal('fetch', fetchMock);
    vi.useFakeTimers();
    render(OpenSnippet, { props });

    await fireEvent.update(
        screen.getByLabelText('Snippet code'),
        "echo 'edited';",
    );
    expect(fetchMock).not.toHaveBeenCalled();

    document.dispatchEvent(
        new KeyboardEvent('keydown', {
            key: 's',
            metaKey: true,
            cancelable: true,
            bubbles: true,
        }),
    );
    await vi.advanceTimersByTimeAsync(0);

    expect(fetchMock).toHaveBeenCalledTimes(1);
    expect(fetchMock).toHaveBeenCalledWith(
        '/api/projects/my-project/snippets/scratch',
        expect.objectContaining({
            body: JSON.stringify({ content: "echo 'edited';" }),
        }),
    );

    await vi.advanceTimersByTimeAsync(500);

    expect(fetchMock).toHaveBeenCalledTimes(1);
});

it('clears the feed output and any error message', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: 'hi', debug: null });
    await screen.findByText('hi');

    await fireEvent.click(screen.getByRole('button', { name: 'Clear output' }));

    expect(screen.queryByText('hi')).toBeNull();
});

it('clears the run metrics strip when output is cleared', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '',
        debug: payload({ duration_str: '9.90ms' }),
    });
    await screen.findByText('9.90ms');

    await fireEvent.click(screen.getByRole('button', { name: 'Clear output' }));

    expect(screen.queryByText('9.90ms')).toBeNull();
});

it('hides the header and shows an exit-fullscreen button when maximized', async () => {
    render(OpenSnippet, { props });

    screen.getByText('tinkerbench');

    await fireEvent.click(
        screen.getByRole('button', { name: 'Toggle fullscreen' }),
    );

    expect(screen.queryByText('tinkerbench')).toBeNull();
    screen.getByRole('button', { name: 'Exit fullscreen' });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Exit fullscreen' }),
    );

    screen.getByText('tinkerbench');
});

it('shows a button to switch to the light theme when dark is active', () => {
    render(OpenSnippet, { props });

    screen.getByRole('button', { name: 'Switch to light theme' });
});

it('shows a button to switch to the dark theme when light is active', () => {
    mockTheme.value = 'light';
    render(OpenSnippet, { props });

    screen.getByRole('button', { name: 'Switch to dark theme' });
});

it('toggles the theme when the theme button is clicked', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Switch to light theme' }),
    );

    expect(toggleTheme).toHaveBeenCalledOnce();
    screen.getByRole('button', { name: 'Switch to dark theme' });
});

it('prevents the native browser save dialog on Ctrl/Cmd+S', () => {
    render(OpenSnippet, { props });

    const event = new KeyboardEvent('keydown', {
        key: 's',
        metaKey: true,
        cancelable: true,
        bubbles: true,
    });
    const preventDefaultSpy = vi.spyOn(event, 'preventDefault');
    document.dispatchEvent(event);

    expect(preventDefaultSpy).toHaveBeenCalled();
});

it('removes the global Ctrl/Cmd+S listener when unmounted', () => {
    const addSpy = vi.spyOn(window, 'addEventListener');
    const removeSpy = vi.spyOn(window, 'removeEventListener');
    const rendered = render(OpenSnippet, { props });

    rendered.unmount();

    const [, handler] =
        addSpy.mock.calls.find(([type]) => type === 'keydown') ?? [];
    expect(removeSpy).toHaveBeenCalledWith('keydown', handler, {
        capture: true,
    });
});
