export interface Shortcut {
    id: 'browse' | 'run';
    keys: string;
    description: string;
}

export const shortcuts: Shortcut[] = [
    { id: 'run', keys: '⌘Enter', description: 'Run snippet' },
    { id: 'browse', keys: '⌘P', description: 'Browse snippets' },
];
