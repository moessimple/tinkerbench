import { expect, it } from 'vitest';
import DumpCard from './DumpCard.vue';
import ExceptionCard from './ExceptionCard.vue';
import { FACET_KINDS, FEED_KINDS, rendererFor } from './kinds';
import LogCard from './LogCard.vue';
import NPlusOneCard from './NPlusOneCard.vue';
import OutputCard from './OutputCard.vue';
import QueryCard from './QueryCard.vue';
import ResultCard from './ResultCard.vue';

it('maps every kind to its renderer', () => {
    expect(rendererFor('dump')).toBe(DumpCard);
    expect(rendererFor('query')).toBe(QueryCard);
    expect(rendererFor('log')).toBe(LogCard);
    expect(rendererFor('exception')).toBe(ExceptionCard);
    expect(rendererFor('n_plus_one')).toBe(NPlusOneCard);
    expect(rendererFor('result')).toBe(ResultCard);
    expect(rendererFor('output')).toBe(OutputCard);
});

it('throws for a kind with no registered renderer', () => {
    // @ts-expect-error deliberately unregistered kind
    expect(() => rendererFor('mystery')).toThrow(
        'No feed renderer registered for kind "mystery".',
    );
});

it('exposes every FEED_KINDS entry through rendererFor', () => {
    for (const entry of FEED_KINDS) {
        expect(rendererFor(entry.kind)).toBe(entry.component);
    }
});

it('keeps the facet-less Result and Output entries out of the facet list', () => {
    expect(FACET_KINDS.map((kind) => kind.kind)).toEqual([
        'dump',
        'query',
        'log',
        'exception',
        'n_plus_one',
    ]);
    expect(FACET_KINDS.every((kind) => typeof kind.facet === 'string')).toBe(
        true,
    );
});
