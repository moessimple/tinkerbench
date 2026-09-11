import { beforeEach, expect, it } from 'vitest';
import { useWatcherToggles } from './useWatcherToggles';

beforeEach(() => {
    localStorage.clear();
});

it('defaults every watcher to off when nothing is stored', () => {
    const { isEnabled } = useWatcherToggles('my-project');

    expect(isEnabled('view')).toBe(false);
});

it('flips a watcher on and persists it to localStorage', () => {
    const { isEnabled, toggle } = useWatcherToggles('my-project');

    toggle('view');

    expect(isEnabled('view')).toBe(true);
    expect(localStorage.getItem('watcher-toggles:my-project')).toBe(
        '{"view":true}',
    );
});

it('flips a watcher back off', () => {
    const { isEnabled, toggle } = useWatcherToggles('my-project');

    toggle('view');
    toggle('view');

    expect(isEnabled('view')).toBe(false);
});

it('reads an already-stored value for the project', () => {
    localStorage.setItem('watcher-toggles:my-project', '{"view":true}');

    const { isEnabled } = useWatcherToggles('my-project');

    expect(isEnabled('view')).toBe(true);
});

it('keeps two different projects independent', () => {
    const projectA = useWatcherToggles('project-a');
    const projectB = useWatcherToggles('project-b');

    projectA.toggle('view');

    expect(projectA.isEnabled('view')).toBe(true);
    expect(projectB.isEnabled('view')).toBe(false);
});

it('ignores malformed stored data instead of throwing', () => {
    localStorage.setItem('watcher-toggles:my-project', 'not json');

    const { isEnabled } = useWatcherToggles('my-project');

    expect(isEnabled('view')).toBe(false);
});

it('ignores validly-parsed stored data that is not an object', () => {
    localStorage.setItem('watcher-toggles:my-project', 'null');

    const { isEnabled } = useWatcherToggles('my-project');

    expect(isEnabled('view')).toBe(false);
});
