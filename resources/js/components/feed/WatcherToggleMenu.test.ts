import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it } from 'vitest';
import WatcherToggleMenu from './WatcherToggleMenu.vue';

it('hides the watcher list until the toggle button is opened', () => {
    render(WatcherToggleMenu, {
        props: { watchers: [{ id: 'view', label: 'Views', enabled: false }] },
    });

    expect(screen.queryByRole('menu')).toBeNull();
});

it('shows every watcher with its current enabled state once opened', async () => {
    render(WatcherToggleMenu, {
        props: {
            watchers: [
                { id: 'view', label: 'Views', enabled: true },
                { id: 'other', label: 'Other', enabled: false },
            ],
        },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Optional watchers' }),
    );

    const checkboxes = screen.getAllByRole('checkbox') as HTMLInputElement[];
    expect(screen.getByText('Views')).toBeTruthy();
    expect(screen.getByText('Other')).toBeTruthy();
    expect(checkboxes[0].checked).toBe(true);
    expect(checkboxes[1].checked).toBe(false);
});

it('emits toggle with the watcher id when its checkbox is clicked', async () => {
    const { emitted } = render(WatcherToggleMenu, {
        props: { watchers: [{ id: 'view', label: 'Views', enabled: false }] },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Optional watchers' }),
    );
    await fireEvent.click(screen.getByRole('checkbox'));

    expect(emitted().toggle).toEqual([['view']]);
});

it('closes the menu when Escape is pressed', async () => {
    render(WatcherToggleMenu, {
        props: { watchers: [{ id: 'view', label: 'Views', enabled: false }] },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Optional watchers' }),
    );
    expect(screen.getByRole('menu')).toBeTruthy();

    await fireEvent.keyDown(document, { key: 'Escape' });

    expect(screen.queryByRole('menu')).toBeNull();
});

it('closes the menu when clicking outside it', async () => {
    render(WatcherToggleMenu, {
        props: { watchers: [{ id: 'view', label: 'Views', enabled: false }] },
    });

    await fireEvent.click(
        screen.getByRole('button', { name: 'Optional watchers' }),
    );
    expect(screen.getByRole('menu')).toBeTruthy();

    await fireEvent.click(document.body);

    expect(screen.queryByRole('menu')).toBeNull();
});
