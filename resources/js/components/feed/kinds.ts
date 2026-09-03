import type { Component } from 'vue';
import type { FeedEntry } from '@/lib/feed';
import DumpCard from './DumpCard.vue';
import ExceptionCard from './ExceptionCard.vue';
import LogCard from './LogCard.vue';
import OutputCard from './OutputCard.vue';
import QueryCard from './QueryCard.vue';
import ResultCard from './ResultCard.vue';

export interface FeedKind {
    kind: FeedEntry['kind'];
    /**
     * Facet tab label and empty-state noun. `null` means the kind has no facet tab and only shows
     * under "all": the synthetic Output entry, and the single Result entry a run produces at most.
     */
    facet: string | null;
    /** Renders one entry of this kind. Receives `entry` and re-emits `navigate`. */
    component: Component;
}

/**
 * The single source of truth for feed entry kinds. Adding an output type is: a new FeedItem
 * variant in `@/types`, a component under this folder, and one row here. `OutputFeed` dispatches
 * on `kind` through this list; `OpenSnippet` derives its facet tabs and counts from it.
 */
export const FEED_KINDS: readonly FeedKind[] = [
    { kind: 'dump', facet: 'Dumps', component: DumpCard },
    { kind: 'query', facet: 'Queries', component: QueryCard },
    { kind: 'log', facet: 'Logs', component: LogCard },
    { kind: 'exception', facet: 'Exceptions', component: ExceptionCard },
    { kind: 'result', facet: null, component: ResultCard },
    { kind: 'output', facet: null, component: OutputCard },
];

/** Facetable kinds in tab order, i.e. everything except the synthetic Output entry. */
export const FACET_KINDS = FEED_KINDS.filter(
    (entry): entry is FeedKind & { facet: string } => entry.facet !== null,
);

export function rendererFor(kind: FeedEntry['kind']): Component {
    const match = FEED_KINDS.find((entry) => entry.kind === kind);

    if (!match) {
        throw new Error(`No feed renderer registered for kind "${kind}".`);
    }

    return match.component;
}
