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
 * Assembles the render list for the output feed: the captured items in execution order, then an
 * Output entry for the snippet's raw stdout when it produced any, then the Result entry for the
 * snippet's return value. Result comes last because it is the run's final value, whatever order the
 * runner recorded it in among the items.
 */
export function buildFeed(
    payload: SnippetDebugPayload,
    rawOutput: string,
): FeedEntry[] {
    const result = payload.items.filter((item) => item.kind === 'result');
    const entries: FeedEntry[] = payload.items.filter(
        (item) => item.kind !== 'result',
    );

    if (rawOutput.trim() !== '') {
        entries.push({ kind: 'output', text: rawOutput });
    }

    entries.push(...result);

    return entries;
}
