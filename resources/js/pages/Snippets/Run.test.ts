import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import { reactive } from 'vue';

let capturedPost: {
    url: string;
    onSuccess: (data: { output: string }) => void;
} | null = null;
let capturedTitle: string | undefined;

// Kept as the single object identity useHttp() returns, so v-model writes in the
// component and reads in this test observe the same reactive state.
const httpState = reactive({
    code: '',
    processing: false,
    post: (
        url: string,
        options: { onSuccess: (data: { output: string }) => void },
    ) => {
        capturedPost = { url, onSuccess: options.onSuccess };
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

const { default: Run } = await import('./Run.vue');

it('sets the page title', () => {
    render(Run);

    expect(capturedTitle).toBe('Snippets');
});

it('sends the entered code to the run endpoint', async () => {
    render(Run);

    await fireEvent.update(
        screen.getByPlaceholderText("echo 'hello world';"),
        "echo 'hi';",
    );
    await fireEvent.click(screen.getByRole('button', { name: 'Run' }));

    expect(capturedPost?.url).toBe('/snippets/executions');
    expect(httpState.code).toBe("echo 'hi';");
});

it('shows the returned output', async () => {
    render(Run);

    await fireEvent.click(screen.getByRole('button', { name: 'Run' }));
    capturedPost?.onSuccess({ output: 'hi' });

    await screen.findByText('hi');
});

it('disables the run button and shows a running label while processing', () => {
    httpState.processing = true;

    render(Run);

    const button = screen.getByRole('button', {
        name: 'Running…',
    }) as HTMLButtonElement;
    expect(button.disabled).toBe(true);

    httpState.processing = false;
});
