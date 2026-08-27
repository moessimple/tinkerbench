import { expect, it } from 'vitest';
import type { SnippetDebugPayload } from '@/types';
import { buildFeed } from './feed';

function payload(items: SnippetDebugPayload['items']): SnippetDebugPayload {
    return { duration_str: '1.00ms', items, peak_memory_str: '1.00MB' };
}

it('passes the payload items through in order', () => {
    const items: SnippetDebugPayload['items'] = [
        { html: '<a/>', kind: 'dump', line: 1 },
        { context: null, kind: 'log', label: 'info', line: 2, message: 'hi' },
    ];

    expect(buildFeed(payload(items), '')).toEqual(items);
});

it('appends an output entry when the raw output is not blank', () => {
    expect(buildFeed(payload([]), 'printed text')).toEqual([
        { kind: 'output', text: 'printed text' },
    ]);
});

it('omits the output entry when the raw output is blank', () => {
    expect(buildFeed(payload([]), '   \n  ')).toEqual([]);
});
