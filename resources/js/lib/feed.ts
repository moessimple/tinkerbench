import type { FeedItem, SnippetDebugPayload } from '@/types';

export interface OutputItem {
    kind: 'output';
    text: string;
}

export type FeedEntry = FeedItem | OutputItem;

/**
 * The output feed's active facet: `'all'` shows every entry, any other value narrows the feed to
 * captured items of that kind. The synthetic Output entry only ever shows under `'all'`.
 */
export type FeedFilter = FeedItem['kind'] | 'all';

/**
 * Feed ordering: `'recent'` keeps execution order, `'slowest'` sorts by query duration descending.
 * Only meaningful alongside the `'query'` filter, since no other entry kind carries a duration.
 */
export type FeedSort = 'recent' | 'slowest';

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
