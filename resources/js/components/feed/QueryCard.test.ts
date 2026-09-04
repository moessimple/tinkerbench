import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { FeedItem } from '@/types';
import QueryCard from './QueryCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves QueryCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant', 'copy'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant" :data-copy="copy">
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
    expect(card?.textContent).toContain('select');
    expect(card?.textContent).toContain('2.00ms');
    expect(card?.textContent).toContain('sqlite');
    expect(card?.textContent?.toLowerCase()).not.toContain('slow');
    expect(card?.textContent?.toLowerCase()).not.toContain('duplicate');
});

it('formats the sql across lines and highlights its keywords', () => {
    const { container } = render(QueryCard, {
        props: {
            entry: query({
                sql: "select id from users where email = 'a@b.com'",
            }),
        },
    });

    const code = container.querySelector('pre');
    expect(code?.textContent).toContain('\n');
    expect(code?.innerHTML).toContain(
        '<span class="text-accent">select</span>',
    );
    expect(code?.innerHTML).toContain(
        `<span class="text-warn">'a@b.com'</span>`,
    );
});

it('hands Card the formatted sql for copying', () => {
    const { container } = render(QueryCard, {
        props: { entry: query({ sql: 'select 1 from users' }) },
    });

    const copy = container
        .querySelector('[data-label="Query"]')
        ?.getAttribute('data-copy');
    expect(copy).toContain('\n');
    expect(copy).toContain('from');
});

it('marks a slow query as a warning and shows the slow chip', () => {
    const { container } = render(QueryCard, {
        props: { entry: query({ slow: true }) },
    });

    const card = container.querySelector('[data-label="Query"]');
    expect(card?.getAttribute('data-variant')).toBe('warning');
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
