import { expect, it } from 'vitest';
import type { SnippetDebugPayload } from '@/types';
import { buildFeed } from './feed';

function payload(items: SnippetDebugPayload['items']): SnippetDebugPayload {
    return { duration_str: '1.00ms', items, peak_memory_str: '1.00 MB' };
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

it('moves the result entry to the very end, after captured items and output', () => {
    const items: SnippetDebugPayload['items'] = [
        { html: '<r/>', kind: 'result' },
        { html: '<a/>', kind: 'dump', line: 1 },
    ];

    expect(buildFeed(payload(items), 'printed text')).toEqual([
        { html: '<a/>', kind: 'dump', line: 1 },
        { kind: 'output', text: 'printed text' },
        { html: '<r/>', kind: 'result' },
    ]);
});

it('keeps the result entry last when the run produced no stdout', () => {
    const items: SnippetDebugPayload['items'] = [
        { html: '<r/>', kind: 'result' },
        { context: null, kind: 'log', label: 'info', line: 1, message: 'hi' },
    ];

    expect(buildFeed(payload(items), '')).toEqual([
        { context: null, kind: 'log', label: 'info', line: 1, message: 'hi' },
        { html: '<r/>', kind: 'result' },
    ]);
});
