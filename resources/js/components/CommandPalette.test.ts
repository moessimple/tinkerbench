import { fireEvent, render, screen } from '@testing-library/vue';
import { beforeEach, expect, it, vi } from 'vitest';
import { reactive } from 'vue';

const routerGet = vi.fn();
const validateSpy = vi.fn();
let capturedCreatePost: {
    url: string;
    onSuccess?: () => void;
    onHttpException?: (response: { status: number; data: unknown }) => void;
    data: { name: string };
} | null = null;

// The real form's transform() runs at both validate() and post() time, so the mock
// has to apply it too, otherwise a test could observe the untransformed name and miss
// a transform bug entirely.
let transformName = (name: string): string => name;

// Mirrors the shape useHttp(...).withPrecognition(...) returns: a reactive form object
// exposing the field(s) as top-level properties plus validate()/invalid()/errors/post().
const createFormState = reactive({
    name: '',
    errors: {} as Record<string, string>,
    invalidFields: [] as string[],
    processing: false,
    validate(field: string) {
        validateSpy(field);

        return createFormState;
    },
    invalid(field: string) {
        return createFormState.invalidFields.includes(field);
    },
    transform(callback: (data: { name: string }) => { name: string }) {
        transformName = (name) => callback({ name }).name;

        return createFormState;
    },
    post(
        url: string,
        options?: {
            onSuccess?: () => void;
            onHttpException?: (response: {
                status: number;
                data: unknown;
            }) => void;
        },
    ) {
        capturedCreatePost = {
            url,
            onSuccess: options?.onSuccess,
            onHttpException: options?.onHttpException,
            data: { name: transformName(createFormState.name) },
        };
    },
    reset() {
        createFormState.name = '';
    },
});

// @inertiajs/vue3's router/useHttp don't work in jsdom (no page navigation, no real HTTP
// client); environment-driven, not a "tested elsewhere" mock.
vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet },
    useHttp: (initial: { name: string }) => {
        createFormState.name = initial.name;

        return { withPrecognition: () => createFormState };
    },
}));

const { default: CommandPalette } = await import('./CommandPalette.vue');

beforeEach(() => {
    routerGet.mockClear();
    validateSpy.mockClear();
    capturedCreatePost = null;
    createFormState.name = '';
    createFormState.errors = {};
    createFormState.invalidFields = [];
    createFormState.processing = false;
    transformName = (name) => name;
});

function jsonResponse(body: unknown, ok = true): Response {
    return { ok, json: () => Promise.resolve(body) } as Response;
}

it('opens the panel via the global Ctrl/Cmd+P shortcut regardless of focus', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });

    await screen.findByRole('dialog', { name: 'Snippets and projects' });
});

it('closes the panel via the global Ctrl/Cmd+P shortcut when already open', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await screen.findByRole('dialog', { name: 'Snippets and projects' });
    await fireEvent.keyDown(document, { key: 'p', metaKey: true });

    expect(screen.queryByRole('dialog', { name: 'Snippets' })).toBeNull();
});

it('ignores auto-repeated Ctrl/Cmd+P keydown events while a key is held', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await screen.findByRole('dialog', { name: 'Snippets and projects' });
    await fireEvent.keyDown(document, {
        key: 'p',
        metaKey: true,
        repeat: true,
    });

    screen.getByRole('dialog', { name: 'Snippets and projects' });
});

it('removes the global keydown listener when unmounted', () => {
    const addSpy = vi.spyOn(window, 'addEventListener');
    const removeSpy = vi.spyOn(window, 'removeEventListener');
    const rendered = render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'scratch', 'zebra'], []));
    render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'zebra'], []));
    render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'scratch', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');

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
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'zebra'], []));
    render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'apple' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.keyDown(input, { key: 'ArrowDown' });
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/my-project/zebra');
});

it('closes the panel when Escape is pressed on the name field', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.keyDown(input, { key: 'Escape' });

    expect(screen.queryByRole('dialog', { name: 'Snippets' })).toBeNull();
});

it('filters the list as the name field is typed into', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'scratch', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'ap');

    screen.getByText('apple');
    expect(screen.queryByText('scratch')).toBeNull();
    expect(screen.queryByText('zebra')).toBeNull();
});

it('resets the highlight to the first match when the filter changes', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'scratch', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'a');

    const options = screen.getAllByRole('option');
    expect(options[0]?.getAttribute('aria-selected')).toBe('true');
});

it('opens the highlighted match when Enter is pressed with a filtered query', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'scratch', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'zeb');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/my-project/zebra');
});

it('creates a snippet when Enter is pressed and the typed name matches nothing', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'new-one');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(capturedCreatePost?.url).toBe('/api/projects/my-project/snippets');
});

it('shows a hint to create the typed name when nothing matches', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'new-one');

    await screen.findByText(
        'No snippet named "new-one" yet. Press Enter to create it.',
    );
});

it('drops the # scope prefix from the create hint', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, '#my-report');

    await screen.findByText(
        'No snippet named "my-report" yet. Press Enter to create it.',
    );
});

it('clears a typed filter when reopened after being closed without acting on it', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'scratch', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'no-match-at-all');
    await fireEvent.keyDown(input, { key: 'Escape' });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    expect(
        (await screen.findByLabelText<HTMLInputElement>('Search snippets'))
            .value,
    ).toBe('#');
    screen.getByText('apple');
    screen.getByText('scratch');
    screen.getByText('zebra');
});

it('clears an in-progress rename when the panel is closed via the global shortcut', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse(['bandana', 'zebra']));
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'zebra' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
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
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    const button = screen.getByRole('button', { name: 'Browse snippets' });

    expect(button.title).toBe('Browse snippets (⌘P)');
});

it('shows a plain tooltip on the switch project button', () => {
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    const button = screen.getByRole('button', { name: 'Switch project' });

    expect(button.title).toBe('Switch project');
});

it('opens directly scoped to snippets when the browse snippets icon is clicked', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['other-project']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByRole('dialog', { name: 'Snippets' });
    screen.getByText('scratch');
    expect(screen.queryByText('other-project')).toBeNull();
});

it('opens directly scoped to projects when the switch project icon is clicked', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['other-project']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Switch project' }),
    );

    await screen.findByRole('dialog', { name: 'Projects' });
    screen.getByText('other-project');
    expect(screen.queryByText('scratch')).toBeNull();
});

it('closes an already-open palette when a scope icon is clicked again', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['other-project']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByRole('dialog', { name: 'Snippets' });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    expect(screen.queryByRole('dialog')).toBeNull();
});

it('closes when clicking the backdrop', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const dialog = await screen.findByRole('dialog', {
        name: 'Snippets',
    });

    await fireEvent.click(dialog.parentElement as HTMLElement);

    expect(screen.queryByRole('dialog', { name: 'Snippets' })).toBeNull();
});

it('does not close when clicking inside the panel', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const dialog = await screen.findByRole('dialog', {
        name: 'Snippets',
    });

    await fireEvent.click(dialog);

    screen.getByRole('dialog', { name: 'Snippets' });
});

it('loads and shows snippet names when opened', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'apple' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('apple');
    screen.getByText('zebra');
});

it('shows a message when no snippets exist', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('No snippets found.');
});

it('navigates to the selected snippet', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['other'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await fireEvent.click(await screen.findByText('other'));

    expect(routerGet).toHaveBeenCalledWith('/my-project/other');
});

it('validates the new snippet name when it changes', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'my-new-snippet');
    await fireEvent.change(input);

    expect(validateSpy).toHaveBeenCalledWith('name');
});

it('shows the server validation message for an invalid new snippet name', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    createFormState.invalidFields = ['name'];
    createFormState.errors = { name: 'The name field format is invalid.' };
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );

    await screen.findByText('The name field format is invalid.');
});

it('creates a new snippet and navigates to it on success', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'my-new-snippet');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(capturedCreatePost?.url).toBe('/api/projects/my-project/snippets');

    capturedCreatePost?.onSuccess?.();

    expect(routerGet).toHaveBeenCalledWith('/my-project/my-new-snippet');
});

it('shows the domain error message when a create is rejected', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, 'taken-name');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    capturedCreatePost?.onHttpException?.({
        status: 409,
        data: { message: "A snippet named 'taken-name' already exists" },
    });

    await screen.findByText("A snippet named 'taken-name' already exists");
    expect(routerGet).not.toHaveBeenCalled();
});

it('strips the # prefix before creating and navigating to a #-scoped new snippet', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('Search snippets');
    await fireEvent.update(input, '#my-new-snippet');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(capturedCreatePost?.data.name).toBe('my-new-snippet');

    capturedCreatePost?.onSuccess?.();

    expect(routerGet).toHaveBeenCalledWith('/my-project/my-new-snippet');
});

it('disables the create input while a create request is processing', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo([], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input =
        await screen.findByLabelText<HTMLInputElement>('Search snippets');

    createFormState.processing = true;
    await vi.waitFor(() => expect(input.disabled).toBe(true));

    createFormState.processing = false;
    await vi.waitFor(() => expect(input.disabled).toBe(false));
});

it('shows a rename input prefilled with the current name when the rename icon is clicked', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    expect(fetch).toHaveBeenCalledTimes(2);
});

it('returns focus to the search field after a rename is cancelled with Escape', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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

    expect(document.activeElement).toBe(
        screen.getByLabelText('Search snippets'),
    );
});

it('does nothing when renaming is cancelled by losing focus', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    expect(fetch).toHaveBeenCalledTimes(2);
});

it('renames the current snippet and navigates to its new name', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse(['renamed']));
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(jsonResponse({ message: 'name taken' }, false));
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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
        .mockResolvedValueOnce(jsonResponse([]))
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
    render(CommandPalette, {
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

it('disables the rename input while its own request is in flight', async () => {
    let resolveRename: (response: Response) => void = () => {};
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse([]))
        .mockReturnValueOnce(
            new Promise<Response>((resolve) => {
                resolveRename = resolve;
            }),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    const input = screen.getByLabelText<HTMLInputElement>('Rename scratch');
    await fireEvent.update(input, 'renamed');
    await fireEvent.keyDown(input, { key: 'Enter' });

    await vi.waitFor(() => expect(input.disabled).toBe(true));

    resolveRename(jsonResponse({ ok: true }));
    await vi.waitFor(() =>
        expect(routerGet).toHaveBeenCalledWith('/my-project/renamed'),
    );
});

it('keeps the rename row open when the input is blurred while its request is in flight', async () => {
    // A browser blurs a focused input the moment it becomes disabled; the row must stay
    // in its renaming state despite that blur, otherwise it would flip back to the normal
    // view while the request is still running (jsdom doesn't emulate the auto-blur itself,
    // so it's fired manually here to prove the guard, not the browser behavior).
    let resolveRename: (response: Response) => void = () => {};
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse([]))
        .mockReturnValueOnce(
            new Promise<Response>((resolve) => {
                resolveRename = resolve;
            }),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    const input = screen.getByLabelText<HTMLInputElement>('Rename scratch');
    await fireEvent.update(input, 'renamed');
    await fireEvent.keyDown(input, { key: 'Enter' });
    await fireEvent.blur(input);

    screen.getByRole('textbox', { name: 'Rename scratch' });

    resolveRename(jsonResponse({ ok: true }));
    await vi.waitFor(() =>
        expect(routerGet).toHaveBeenCalledWith('/my-project/renamed'),
    );
});

it('shows a delete confirmation when the delete icon is clicked', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    expect(fetch).toHaveBeenCalledTimes(2);
});

it('does nothing when delete is cancelled with Escape', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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
    expect(fetch).toHaveBeenCalledTimes(2);
});

it('returns focus to the search field after a delete confirmation is dismissed', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], []));
    render(CommandPalette, {
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

    expect(document.activeElement).toBe(
        screen.getByLabelText('Search snippets'),
    );
});

it('deletes the current snippet and navigates to the project root', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse([]));
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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
        .mockResolvedValueOnce(jsonResponse([]))
        .mockResolvedValueOnce(
            jsonResponse({ message: 'delete failed' }, false),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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

it('disables the delete confirm button while its own request is in flight', async () => {
    let resolveDelete: (response: Response) => void = () => {};
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse([]))
        .mockReturnValueOnce(
            new Promise<Response>((resolve) => {
                resolveDelete = resolve;
            }),
        );
    vi.stubGlobal('fetch', fetchMock);
    render(CommandPalette, {
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
        expect(
            screen.getByRole<HTMLButtonElement>('button', { name: 'Yes' })
                .disabled,
        ).toBe(true),
    );

    resolveDelete(jsonResponse({ ok: true }));
    await vi.waitFor(() =>
        expect(routerGet).toHaveBeenCalledWith('/my-project'),
    );
});

function fetchRoutedTo(
    snippetNames: string[],
    projectNames: string[],
): ReturnType<typeof vi.fn> {
    return vi.fn((url: string) =>
        Promise.resolve(
            jsonResponse(
                url.includes('/snippets') ? snippetNames : projectNames,
            ),
        ),
    );
}

it('filters snippets the same way with an explicit # prefix', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['apple', 'zebra'], []));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'apple' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await fireEvent.update(input, '#ze');

    await screen.findByText('zebra');
    expect(screen.queryByText('apple')).toBeNull();
});

it('shows projects and filters them by substring when the field starts with /', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['apple', 'zebra']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await fireEvent.update(input, '/ze');

    await screen.findByText('zebra');
    expect(screen.queryByText('apple')).toBeNull();
    expect(screen.queryByText('scratch')).toBeNull();
});

it('navigates to the selected project when Enter is pressed with a match', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['apple', 'zebra']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await fireEvent.update(input, '/zeb');
    await screen.findByText('zebra');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/zebra');
});

it('shows "No projects found." with no create action when nothing matches', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['apple', 'zebra']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await fireEvent.update(input, '/nope');
    await screen.findByText('No projects found.');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).not.toHaveBeenCalled();
    expect(capturedCreatePost).toBeNull();
});

it('shows the project switcher placeholder while in the project category', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['apple', 'zebra']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await fireEvent.update(input, '/');

    await screen.findByLabelText('Switch to project');
});

it('shows snippets and projects grouped into labeled sections by default', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['apple', 'zebra']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    await screen.findByText('scratch');

    screen.getByText('Snippets');
    screen.getByText('Projects');
    screen.getByText('apple');
    screen.getByText('zebra');
});

it('filters both sections at once when typing without a prefix', async () => {
    vi.stubGlobal(
        'fetch',
        fetchRoutedTo(['apple', 'scratch'], ['apple-project', 'zebra']),
    );
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await fireEvent.update(input, 'app');

    await screen.findByRole('option', { name: 'apple' });
    screen.getByRole('option', { name: 'apple-project' });
    expect(screen.queryByText('scratch')).toBeNull();
    expect(screen.queryByText('zebra')).toBeNull();
});

it('moves the highlight from the snippets section into the projects section', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['other']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await screen.findByText('other');

    await fireEvent.keyDown(input, { key: 'ArrowDown' });

    expect(
        screen
            .getByRole('option', { name: 'other' })
            .getAttribute('aria-selected'),
    ).toBe('true');
});

it('switches to the highlighted project when Enter is pressed after navigating past all snippets', async () => {
    vi.stubGlobal('fetch', fetchRoutedTo(['scratch'], ['other']));
    render(CommandPalette, {
        props: { currentProject: 'my-project', currentSnippet: 'scratch' },
    });

    await fireEvent.keyDown(document, { key: 'p', metaKey: true });
    const input = await screen.findByLabelText('Search or jump to');
    await screen.findByText('other');
    await fireEvent.keyDown(input, { key: 'ArrowDown' });
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/other');
});
