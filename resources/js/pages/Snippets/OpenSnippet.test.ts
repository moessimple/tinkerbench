import { fireEvent, render, screen } from '@testing-library/vue';
import { afterEach, beforeEach, expect, it, vi } from 'vitest';
import { reactive } from 'vue';
import type * as OutputModule from '@/lib/output';

let capturedPost: {
    url: string;
    onSuccess: (data: { output: string }) => void;
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
            onSuccess: (data: { output: string }) => void;
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

// MonacoEditor has its own test (MonacoEditor.test.ts) proving it renders the editor and
// emits `change`/`run`; here it's replaced with a plain textarea plus a button so this test
// can drive the same contract without loading real Monaco.
vi.mock('@/components/MonacoEditor.vue', () => ({
    default: {
        props: ['initialValue'],
        emits: ['change', 'run'],
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

// detectOutput/highlightJson are cheap, deterministic pure functions and run for real.
// executeScripts has its own test (output.test.ts) proving it replaces <script> nodes so
// browsers re-run them; jsdom never executes those scripts regardless, so it's mocked here
// to prove only that OpenSnippet.vue calls it at the right time, not that it works.
vi.mock('@/lib/output', async (importOriginal) => ({
    ...(await importOriginal<typeof OutputModule>()),
    executeScripts: vi.fn(),
}));

const { default: OpenSnippet } = await import('./OpenSnippet.vue');
const props = {
    content: "echo 'hello world';",
    currentProject: 'my-project',
    laravelVersion: '13.0.0',
    phpVersion: '8.5.0',
    snippetName: 'scratch',
};

beforeEach(() => {
    capturedPost = null;
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

    expect(capturedPost?.url).toBe('/projects/my-project/snippets/executions');
    expect(httpState.code).toBe("echo 'hi';");
});

it('shows the returned output', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: 'hi' });

    await screen.findByText('hi');
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

    expect(capturedPost?.url).toBe('/projects/my-project/snippets/executions');
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

it('clears the output and any error message', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: 'hi' });
    await screen.findByText('hi');

    await fireEvent.click(screen.getByRole('button', { name: 'Clear output' }));

    expect(screen.queryByText('hi')).toBeNull();
});

it('shows the output as escaped text in raw mode', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '<strong>hi</strong>' });

    await screen.findByText('<strong>hi</strong>');
});

it('renders HTML output as a sandboxed iframe after switching to rendered mode', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '<strong>hi</strong>' });
    await screen.findByText('<strong>hi</strong>');

    await fireEvent.click(
        screen.getByRole('button', { name: 'Show rendered output' }),
    );

    const frame = screen.getByTitle('Rendered HTML output');
    expect(frame.getAttribute('srcdoc')).toBe('<strong>hi</strong>');
    expect(frame.getAttribute('sandbox')).toBe('allow-scripts');
});

it('renders JSON output as pretty-printed, escaped text in rendered mode', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({
        output: '{"name":"<script>unsafe()</script>"}',
    });
    await fireEvent.click(
        screen.getByRole('button', { name: 'Show rendered output' }),
    );

    const output = screen.getByRole('status', { name: 'Snippet output' });
    expect(output.textContent).toBe(
        '{\n  "name": "<script>unsafe()</script>"\n}',
    );
    expect(output.querySelector('script')).toBeNull();
});

it('leaves JSON output as its original raw string in raw mode', async () => {
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '{"name":"tinkerbench"}' });

    await screen.findByText('{"name":"tinkerbench"}');
});

it('re-executes embedded scripts when dump output is shown in rendered mode', async () => {
    const { executeScripts } = await import('@/lib/output');
    render(OpenSnippet, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '<script>Sfdump("sf-dump-1")</script>' });
    await fireEvent.click(
        screen.getByRole('button', { name: 'Show rendered output' }),
    );

    await vi.waitFor(() => expect(executeScripts).toHaveBeenCalledOnce());
});

it('re-executes embedded scripts when a run completes while already in rendered mode', async () => {
    const { executeScripts } = await import('@/lib/output');
    render(OpenSnippet, { props });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Show rendered output' }),
    );
    await fireEvent.click(screen.getByRole('button', { name: 'Run snippet' }));
    capturedPost?.onSuccess({ output: '<script>Sfdump("sf-dump-1")</script>' });

    await vi.waitFor(() => expect(executeScripts).toHaveBeenCalledOnce());
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
