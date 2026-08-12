import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import ShortcutsHelp from './ShortcutsHelp.vue';

it('opens the overview listing all shortcuts when the button is clicked', async () => {
    render(ShortcutsHelp);

    await fireEvent.click(
        screen.getByRole('button', { name: 'Keyboard shortcuts' }),
    );

    screen.getByText('Run snippet');
    screen.getByText('⌘Enter');
    screen.getByText('Browse snippets');
    screen.getByText('⌘P');
});

it('opens the overview via the ? shortcut when no input is focused', async () => {
    render(ShortcutsHelp);

    await fireEvent.keyDown(document, { key: '?' });

    screen.getByRole('dialog', { name: 'Keyboard shortcuts' });
});

it('does not open via ? while a text input is focused', async () => {
    render(ShortcutsHelp);
    const input = document.createElement('input');
    document.body.appendChild(input);
    input.focus();

    await fireEvent.keyDown(input, { key: '?' });

    expect(
        screen.queryByRole('dialog', { name: 'Keyboard shortcuts' }),
    ).toBeNull();
    input.remove();
});

it('closes when the button is clicked again', async () => {
    render(ShortcutsHelp);

    await fireEvent.click(
        screen.getByRole('button', { name: 'Keyboard shortcuts' }),
    );
    await fireEvent.click(
        screen.getByRole('button', { name: 'Keyboard shortcuts' }),
    );

    expect(
        screen.queryByRole('dialog', { name: 'Keyboard shortcuts' }),
    ).toBeNull();
});

it('closes when clicking the backdrop', async () => {
    render(ShortcutsHelp);

    await fireEvent.click(
        screen.getByRole('button', { name: 'Keyboard shortcuts' }),
    );
    const dialog = await screen.findByRole('dialog', {
        name: 'Keyboard shortcuts',
    });

    await fireEvent.click(dialog.parentElement as HTMLElement);

    expect(
        screen.queryByRole('dialog', { name: 'Keyboard shortcuts' }),
    ).toBeNull();
});

it('closes via Escape while open', async () => {
    render(ShortcutsHelp);

    await fireEvent.click(
        screen.getByRole('button', { name: 'Keyboard shortcuts' }),
    );
    await fireEvent.keyDown(document, { key: 'Escape' });

    expect(
        screen.queryByRole('dialog', { name: 'Keyboard shortcuts' }),
    ).toBeNull();
});

it('removes the global keydown listener when unmounted', () => {
    const addSpy = vi.spyOn(window, 'addEventListener');
    const removeSpy = vi.spyOn(window, 'removeEventListener');
    const rendered = render(ShortcutsHelp);

    rendered.unmount();

    const [, handler] =
        addSpy.mock.calls.find(([type]) => type === 'keydown') ?? [];
    expect(removeSpy).toHaveBeenCalledWith('keydown', handler, {
        capture: true,
    });
});
