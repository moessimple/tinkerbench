import { expect, it } from 'vitest';
import { shortcuts } from './shortcuts';

it('lists the run and browse shortcuts', () => {
    expect(shortcuts).toEqual([
        { keys: '⌘Enter', description: 'Run snippet' },
        { keys: '⌘P', description: 'Browse snippets' },
    ]);
});
