import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { FeedItem } from '@/types';
import QueryCard from './QueryCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves QueryCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant">
            <slot /><slot name="footer" />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

function query(overrides: Partial<Extract<FeedItem, { kind: 'query' }>> = {}) {
    return {
        connection: 'sqlite',
        duplicate: false,
        duration_ms: 2,
        duration_str: '2.00ms',
        kind: 'query' as const,
        line: 4,
        slow: false,
        sql: 'select 1',
        ...overrides,
    };
}

it('shows the sql, duration and connection for a routine query', () => {
    const { container } = render(QueryCard, { props: { entry: query() } });

    const card = container.querySelector('[data-label="Query"]');
    expect(card?.getAttribute('data-variant')).toBe('default');
    expect(card?.textContent).toContain('select 1');
    expect(card?.textContent).toContain('2.00ms');
    expect(card?.textContent).toContain('sqlite');
    expect(card?.textContent?.toLowerCase()).not.toContain('slow');
    expect(card?.textContent?.toLowerCase()).not.toContain('duplicate');
});

it('marks a slow query danger and shows the slow chip', () => {
    const { container } = render(QueryCard, {
        props: { entry: query({ slow: true }) },
    });

    const card = container.querySelector('[data-label="Query"]');
    expect(card?.getAttribute('data-variant')).toBe('danger');
    expect(card?.textContent?.toLowerCase()).toContain('slow');
});

it('shows the duplicate chip without changing the variant', () => {
    const { container } = render(QueryCard, {
        props: { entry: query({ duplicate: true }) },
    });

    const card = container.querySelector('[data-label="Query"]');
    expect(card?.getAttribute('data-variant')).toBe('default');
    expect(card?.textContent?.toLowerCase()).toContain('duplicate');
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(QueryCard, {
        props: { entry: query({ line: 12 }) },
    });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[12]]);
});
