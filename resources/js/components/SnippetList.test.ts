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

it('opens the panel via the global Ctrl/Cmd+P shortcut regardless of focus', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });

    await screen.findByRole('dialog', { name: 'Snippets' });
});

it('closes the panel via the global Ctrl/Cmd+P shortcut when already open', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await screen.findByRole('dialog', { name: 'Snippets' });
    await fireEvent.keyDown(document, { key: 'p', metaKey: true });

    expect(screen.queryByRole('dialog', { name: 'Snippets' })).toBeNull();
});

it('ignores auto-repeated Ctrl/Cmd+P keydown events while a key is held', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await screen.findByRole('dialog', { name: 'Snippets' });
    await fireEvent.keyDown(document, {
        key: 'p',
        metaKey: true,
        repeat: true,
    });

    screen.getByRole('dialog', { name: 'Snippets' });
});

it('removes the global keydown listener when unmounted', () => {
    const addSpy = vi.spyOn(window, 'addEventListener');
    const removeSpy = vi.spyOn(window, 'removeEventListener');
    const rendered = render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    rendered.unmount();

    const [, handler] =
        addSpy.mock.calls.find(([type]) => type === 'keydown') ?? [];
    expect(removeSpy).toHaveBeenCalledWith('keydown', handler, {
        capture: true,
    });
});

it('starts with the current snippet highlighted when the panel opens', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'scratch', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');

    const options = screen.getAllByRole('option');
    expect(options[1]?.getAttribute('aria-selected')).toBe('true');
});

it('highlights the first snippet when the current one is not in the list', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'not-in-list' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('apple');

    const options = screen.getAllByRole('option');
    expect(options[0]?.getAttribute('aria-selected')).toBe('true');
});

it('moves the highlight down and up through the list, wrapping at the ends', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'scratch', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');

    await fireEvent.keyDown(input, { key: 'ArrowDown' });
    expect(
        screen.getAllByRole('option')[2]?.getAttribute('aria-selected'),
    ).toBe('true');

    await fireEvent.keyDown(input, { key: 'ArrowDown' });
    expect(
        screen.getAllByRole('option')[0]?.getAttribute('aria-selected'),
    ).toBe('true');

    await fireEvent.keyDown(input, { key: 'ArrowUp' });
    expect(
        screen.getAllByRole('option')[2]?.getAttribute('aria-selected'),
    ).toBe('true');
});

it('sets the highlight on mouse hover', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'apple' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('zebra');

    await fireEvent.mouseEnter(screen.getAllByRole('option')[1] as HTMLElement);

    expect(
        screen.getAllByRole('option')[1]?.getAttribute('aria-selected'),
    ).toBe('true');
});

it('opens the highlighted snippet when Enter is pressed with an empty name field', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'apple' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.keyDown(input, { key: 'ArrowDown' });
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/my-project/zebra');
});

it('closes the panel when Escape is pressed on the name field', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.keyDown(input, { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Snippets' })).toBeNull();
});

it('filters the list as the name field is typed into', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'scratch', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'ap');

    screen.getByText('apple');
    expect(screen.queryByText('scratch')).toBeNull();
    expect(screen.queryByText('zebra')).toBeNull();
});

it('resets the highlight to the first match when the filter changes', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'scratch', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'a');

    const options = screen.getAllByRole('option');
    expect(options[0]?.getAttribute('aria-selected')).toBe('true');
});

it('opens the highlighted match when Enter is pressed with a filtered query', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'scratch', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'zeb');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/my-project/zebra');
});

it('creates a snippet when Enter is pressed and the typed name matches nothing', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'new-one');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(capturedCreatePost?.url).toBe('/api/projects/my-project/snippets');
});

it('shows a hint to create the typed name when nothing matches', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'new-one');

    await screen.findByText(
        'No matches for "new-one". Press Enter to create it.',
    );
});

it('clears a typed filter when reopened after being closed without acting on it', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'scratch', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'no-match-at-all');
    await fireEvent.keyDown(input, { key: 'Escape' });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    expect(
        (await screen.findByLabelText<HTMLInputElement>('New snippet name'))
            .value,
    ).toBe('');
    screen.getByText('apple');
    screen.getByText('scratch');
    screen.getByText('zebra');
});

it('clears an in-progress rename when the panel is closed via the global shortcut', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await fireEvent.keyDown(document, { key: 'p', metaKey: true });

    await screen.findByText('scratch');
    expect(
        screen.queryByRole('textbox', { name: 'Rename scratch' }),
    ).toBeNull();
});

it('clears an in-progress delete confirmation when the panel is closed via the global shortcut', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await fireEvent.keyDown(document, { key: 'p', metaKey: true });

    await screen.findByText('scratch');
    expect(screen.queryByText("Delete 'scratch'?")).toBeNull();
});

it('keeps the highlight in bounds after deleting a filtered, non-current snippet', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['banana', 'bandana', 'zebra']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse(['bandana', 'zebra']));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'zebra' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'ban');
    await screen.findByText('banana');

    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete banana' }),
    );
    await fireEvent.click(screen.getByRole('button', { name: 'Yes' }));

    await vi.waitFor(() => expect(screen.queryByText('banana')).toBeNull());
    const options = screen.getAllByRole('option');
    expect(options).toHaveLength(1);
    expect(options[0]?.getAttribute('aria-selected')).toBe('true');
});

it('shows the shortcut in the browse button tooltip', () => {
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    const button = screen.getByRole('button', { name: 'Browse snippets' });

    expect(button.title).toBe('Browse snippets (⌘P)');
});

it('closes when clicking the backdrop', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Snippets' });

    await fireEvent.click(dialog.parentElement as HTMLElement);

    expect(screen.queryByRole('dialog', { name: 'Snippets' })).toBeNull();
});

it('does not close when clicking inside the panel', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const dialog = await screen.findByRole('dialog', { name: 'Snippets' });

    await fireEvent.click(dialog);

    screen.getByRole('dialog', { name: 'Snippets' });
});

it('loads and shows snippet names when opened', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['apple', 'zebra'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'apple' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('apple');
    screen.getByText('zebra');
});

it('shows a message when no snippets exist', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('No snippets found.');
});

it('navigates to the selected snippet', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse(['other'])));
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await fireEvent.click(await screen.findByText('other'));

    expect(routerGet).toHaveBeenCalledWith('/my-project/other');
});

it('validates the new snippet name when it changes', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('The name field format is invalid.');
});

it('creates a new snippet and navigates to it on success', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'my-new-snippet');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(capturedCreatePost?.url).toBe('/api/projects/my-project/snippets');

    capturedCreatePost?.onSuccess?.();

    expect(routerGet).toHaveBeenCalledWith('/my-project/my-new-snippet');
});

it('shows a rename input prefilled with the current name when the rename icon is clicked', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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

    await vi.waitFor(() =>
        expect(routerGet).toHaveBeenCalledWith('/my-project/renamed'),
    );
});

it('reloads the list after renaming a snippet that is not the current one', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['other']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse(['renamed']));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

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

it('shows a delete confirmation when the delete icon is clicked', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );

    await screen.findByText("Delete 'scratch'?");
});

it('does nothing when delete is cancelled with No', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );
    await fireEvent.click(screen.getByRole('button', { name: 'No' }));

    screen.getByRole('button', { name: 'Delete scratch' });
    expect(fetch).toHaveBeenCalledTimes(1);
});

it('does nothing when delete is cancelled with Escape', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );
    await fireEvent.keyDown(screen.getByRole('button', { name: 'No' }), {
        key: 'Escape',
    });

    screen.getByRole('button', { name: 'Delete scratch' });
    expect(fetch).toHaveBeenCalledTimes(1);
});

it('deletes the current snippet and navigates to the project root', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );
    await fireEvent.click(screen.getByRole('button', { name: 'Yes' }));

    await vi.waitFor(() =>
        expect(routerGet).toHaveBeenCalledWith('/my-project'),
    );
});

it('reloads the list after deleting a snippet that is not the current one', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['other']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse([]));
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('other');
    await fireEvent.click(screen.getByRole('button', { name: 'Delete other' }));
    await fireEvent.click(screen.getByRole('button', { name: 'Yes' }));

    await screen.findByText('No snippets found.');
    expect(routerGet).not.toHaveBeenCalled();
});

it('shows the error message inline when deleting fails', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(
            jsonResponse({ ok: false, error: 'delete failed' }, false),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(SnippetList, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Delete scratch' }),
    );
    await fireEvent.click(screen.getByRole('button', { name: 'Yes' }));

    await screen.findByText('delete failed');
});
