import { fireEvent, render, screen } from '@testing-library/vue';
import { beforeEach, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

const routerGet = vi.fn();
const validateSpy = vi.fn();
let capturedCreatePost: { url: string; onSuccess?: () => void } | null = null;

// Mirrors the shape useHttp(...).withPrecognition(...) returns: a reactive form object
// exposing the field(s) as top-level properties plus validate()/invalid()/errors/post().
const createFormState = reactive({
    name: '',
    errors: {} as Record<string, string>,
    invalidFields: [] as string[],
    validate(field: string) {
        validateSpy(field);

        return createFormState;
    },
    invalid(field: string) {
        return createFormState.invalidFields.includes(field);
    },
    post(url: string, options?: { onSuccess?: () => void }) {
        capturedCreatePost = { url, onSuccess: options?.onSuccess };
    },
    reset() {
        createFormState.name = '';
    },
});

vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet },
    useHttp: (initial: { name: string }) => {
        createFormState.name = initial.name;

        return { withPrecognition: () => createFormState };
    },
}));

const { default: SnippetList } = await import('./SnippetList.vue');

beforeEach(() => {
    routerGet.mockClear();
    validateSpy.mockClear();
    capturedCreatePost = null;
    createFormState.name = '';
    createFormState.errors = {};
    createFormState.invalidFields = [];
});

function jsonResponse(body: unknown, ok = true): Response {
    return { ok, json: () => Promise.resolve(body) } as Response;
}

it('loads and shows snippet names when opened', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'zebra'])),
    );
    render(SnippetList, { props: { currentSnippet: 'apple' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('apple');
    screen.getByText('zebra');
});

it('shows a message when no snippets exist', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('No snippets found.');
});

it('navigates to the selected snippet', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(['other'])));
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await fireEvent.click(await screen.findByText('other'));

    expect(routerGet).toHaveBeenCalledWith('/other');
});

it('validates the new snippet name when it changes', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'my-new-snippet');
    await fireEvent.change(input);

    expect(validateSpy).toHaveBeenCalledWith('name');
});

it('shows the server validation message for an invalid new snippet name', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    createFormState.invalidFields = ['name'];
    createFormState.errors = { name: 'The name field format is invalid.' };
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('The name field format is invalid.');
});

it('creates a new snippet and navigates to it on success', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'my-new-snippet');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(capturedCreatePost?.url).toBe('/api/snippets');

    capturedCreatePost?.onSuccess?.();

    expect(routerGet).toHaveBeenCalledWith('/my-new-snippet');
});

it('shows a rename input prefilled with the current name when the rename icon is clicked', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );

    expect(
        screen.getByLabelText<HTMLInputElement>('Rename scratch').value,
    ).toBe('scratch');
});

it('does nothing when renaming is cancelled with Escape', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    await fireEvent.keyDown(screen.getByLabelText('Rename scratch'), {
        key: 'Escape',
    });

    expect(
        screen.queryByRole('textbox', { name: 'Rename scratch' }),
    ).toBeNull();
    expect(fetch).toHaveBeenCalledTimes(1);
});

it('does nothing when renaming is cancelled by losing focus', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    await fireEvent.blur(screen.getByLabelText('Rename scratch'));

    expect(
        screen.queryByRole('textbox', { name: 'Rename scratch' }),
    ).toBeNull();
    expect(fetch).toHaveBeenCalledTimes(1);
});

it('renames the current snippet and navigates to its new name', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    const input = screen.getByLabelText('Rename scratch');
    await fireEvent.update(input, 'renamed');
    await fireEvent.keyDown(input, { key: 'Enter' });

    await vi.waitFor(() => expect(routerGet).toHaveBeenCalledWith('/renamed'));
});

it('reloads the list after renaming a snippet that is not the current one', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['other']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse(['renamed']));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('other');
    await fireEvent.click(screen.getByRole('button', { name: 'Rename other' }));
    const input = screen.getByLabelText('Rename other');
    await fireEvent.update(input, 'renamed');
    await fireEvent.keyDown(input, { key: 'Enter' });

    await screen.findByText('renamed');
    expect(routerGet).not.toHaveBeenCalled();
});

it('shows the domain error message inline when renaming fails', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(
            jsonResponse({ ok: false, error: 'name taken' }, false),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    const input = screen.getByLabelText('Rename scratch');
    await fireEvent.update(input, 'taken');
    await fireEvent.keyDown(input, { key: 'Enter' });

    await screen.findByText('name taken');
});

it('shows the server validation message inline when renaming fails validation', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(
            jsonResponse(
                {
                    message: 'The given data was invalid.',
                    errors: { name: ['Too long.'] },
                },
                false,
            ),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    const input = screen.getByLabelText('Rename scratch');
    await fireEvent.update(input, 'a'.repeat(201));
    await fireEvent.keyDown(input, { key: 'Enter' });

    await screen.findByText('Too long.');
});

it('does nothing when delete is not confirmed', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    vi.spyOn(window, 'confirm').mockReturnValue(false);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );

    expect(fetch).toHaveBeenCalledTimes(1);
});

it('deletes the current snippet and navigates home', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );

    await vi.waitFor(() => expect(routerGet).toHaveBeenCalledWith('/'));
});

it('reloads the list after deleting a snippet that is not the current one', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['other']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse([]));
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('other');
    await fireEvent.click(screen.getByRole('button', { name: 'Delete other' }));

    await screen.findByText('No snippets found.');
    expect(routerGet).not.toHaveBeenCalled();
});

it('shows an alert when deleting fails', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(
            jsonResponse({ ok: false, error: 'delete failed' }, false),
        );
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(window, 'confirm').mockReturnValue(true);
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );

    await vi.waitFor(() =>
        expect(alertSpy).toHaveBeenCalledWith('delete failed'),
    );
});
