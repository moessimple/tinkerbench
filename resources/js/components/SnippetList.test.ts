import { fireEvent, render, screen } from '@testing-library/vue';
import { beforeEach, expect, it, vi } from 'vitest';

const routerGet = vi.fn();

vi.mock('@inertiajs/vue3', () => ({
    router: { get: routerGet },
}));

const { default: SnippetList } = await import('./SnippetList.vue');

beforeEach(() => {
    routerGet.mockClear();
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

it('navigates to a new snippet with a valid name', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'my-new-snippet');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).toHaveBeenCalledWith('/my-new-snippet');
});

it('does not navigate when the new snippet name is invalid', async () => {
    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(jsonResponse([])));
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    const input = await screen.findByLabelText('New snippet name');
    await fireEvent.update(input, 'invalid name!');
    await fireEvent.submit(input.closest('form') as HTMLFormElement);

    expect(routerGet).not.toHaveBeenCalled();
});

it('does nothing when the rename prompt is cancelled', async () => {
    vi.stubGlobal(
        'fetch',
        vi.fn().mockResolvedValue(jsonResponse(['scratch'])),
    );
    vi.spyOn(window, 'prompt').mockReturnValue(null);
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );

    expect(fetch).toHaveBeenCalledTimes(1);
});

it('renames the current snippet and navigates to its new name', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }));
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(window, 'prompt').mockReturnValue('renamed');
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );
    await vi.waitFor(() => expect(routerGet).toHaveBeenCalledWith('/renamed'));
});

it('reloads the list after renaming a snippet that is not the current one', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['other']))
        .mockResolvedValueOnce(jsonResponse({ ok: true }))
        .mockResolvedValueOnce(jsonResponse(['renamed']));
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(window, 'prompt').mockReturnValue('renamed');
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('other');
    await fireEvent.click(screen.getByRole('button', { name: 'Rename other' }));

    await screen.findByText('renamed');
    expect(routerGet).not.toHaveBeenCalled();
});

it('shows an alert when renaming fails', async () => {
    const fetchMock = vi
        .fn()
        .mockResolvedValueOnce(jsonResponse(['scratch']))
        .mockResolvedValueOnce(
            jsonResponse({ ok: false, error: 'name taken' }),
        );
    vi.stubGlobal('fetch', fetchMock);
    vi.spyOn(window, 'prompt').mockReturnValue('taken');
    const alertSpy = vi.spyOn(window, 'alert').mockImplementation(() => {});
    render(SnippetList, { props: { currentSnippet: 'scratch' } });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Browse snippets' }),
    );
    await screen.findByText('scratch');
    await fireEvent.click(
        screen.getByRole('button', { name: 'Rename scratch' }),
    );

    await vi.waitFor(() => expect(alertSpy).toHaveBeenCalledWith('name taken'));
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
            jsonResponse({ ok: false, error: 'delete failed' }),
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
