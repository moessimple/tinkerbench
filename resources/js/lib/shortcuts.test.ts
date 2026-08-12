import { expect, it } from 'vitest';
import { shortcuts } from './shortcuts';

it('lists the run, browse, and help shortcuts', () => {
    expect(shortcuts).toEqual([
        { keys: '⌘Enter', description: 'Run snippet' },
        { keys: '⌘P', description: 'Browse snippets' },
        { keys: '?', description: 'Keyboard shortcuts' },
    ]);
});
