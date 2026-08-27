import type { FeedItem, SnippetDebugPayload } from '@/types';

export interface OutputItem {
    kind: 'output';
    text: string;
}

export type FeedEntry = FeedItem | OutputItem;

/**
 * Assembles the render list for the output feed: the captured items in execution order, followed by
 * an Output entry for the snippet's raw stdout when it produced any.
 */
export function buildFeed(
    payload: SnippetDebugPayload,
    rawOutput: string,
): FeedEntry[] {
    const entries: FeedEntry[] = [...payload.items];

    if (rawOutput.trim() !== '') {
        entries.push({ kind: 'output', text: rawOutput });
    }

    return entries;
}
