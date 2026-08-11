import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import { reactive } from 'vue';

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
    useHttp: () => httpState,
}));

// MonacoEditor has its own test (MonacoEditor.test.ts) proving it renders the editor and
// emits `change`; here it's replaced with a plain textarea so this test can drive the same
// contract without loading real Monaco.
vi.mock('@/components/MonacoEditor.vue', () => ({
    default: {
        props: ['initialValue'],
        emits: ['change'],
        template:
            '<textarea aria-label="Snippet code" :value="initialValue" @input="$emit(\'change\', $event.target.value)" />',
    },
}));

const { default: Run } = await import('./Run.vue');
const props = { laravelVersion: '13.0.0', phpVersion: '8.5.0' };

it('sets the page title', () => {
    render(Run, { props });

    expect(capturedTitle).toBe('Snippets');
});

it('shows the running PHP and Laravel version', () => {
    render(Run, { props });

    screen.getByText('PHP 8.5.0 · Laravel 13.0.0');
});

it('sends the entered code to the run endpoint', async () => {
    render(Run, { props });

    await fireEvent.update(screen.getByLabelText('Snippet code'), "echo 'hi';");
    await fireEvent.click(screen.getByRole('button', { name: 'Run' }));

    expect(capturedPost?.url).toBe('/snippets/executions');
    expect(httpState.code).toBe("echo 'hi';");
});

it('shows the returned output', async () => {
    render(Run, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run' }));
    capturedPost?.onSuccess({ output: 'hi' });

    await screen.findByText('hi');
});

it('shows a validation error message when the request fails', async () => {
    render(Run, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run' }));
    capturedPost?.onError({ code: 'The code field is required.' });

    await screen.findByText('The code field is required.');
});

it('shows a generic error message when the server request fails', async () => {
    render(Run, { props });

    await fireEvent.click(screen.getByRole('button', { name: 'Run' }));
    capturedPost?.onHttpException({ status: 500 });

    await screen.findByText('Request failed (500).');
});

it('disables the run button and shows a running label while processing', () => {
    httpState.processing = true;

    render(Run, { props });

    const button = screen.getByRole('button', {
        name: 'Running…',
    }) as HTMLButtonElement;
    expect(button.disabled).toBe(true);

    httpState.processing = false;
});
