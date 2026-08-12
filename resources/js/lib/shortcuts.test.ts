import { expect, it } from 'vitest';
import { shortcuts } from './shortcuts';

it('lists the run and browse shortcuts', () => {
    expect(shortcuts).toEqual([
        { id: 'run', keys: '⌘Enter', description: 'Run snippet' },
        { id: 'browse', keys: '⌘P', description: 'Browse snippets' },
    ]);
});
