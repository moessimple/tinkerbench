export interface Shortcut {
    keys: string;
    description: string;
}

export const shortcuts: Shortcut[] = [
    { keys: '⌘Enter', description: 'Run snippet' },
    { keys: '⌘P', description: 'Browse snippets' },
];
