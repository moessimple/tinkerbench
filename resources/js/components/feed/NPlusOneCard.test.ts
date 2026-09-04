import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { FeedItem } from '@/types';
import NPlusOneCard from './NPlusOneCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves NPlusOneCard's content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant">
            <slot />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

function finding(): Extract<FeedItem, { kind: 'n_plus_one' }> {
    return {
        count: 12,
        kind: 'n_plus_one',
        line: 7,
        model: 'App\\Models\\User',
        relation: 'posts',
    };
}

it('names the model, relation and access count on the danger variant', () => {
    const { container } = render(NPlusOneCard, { props: { entry: finding() } });

    const card = container.querySelector('[data-label="N+1"]');
    expect(card?.getAttribute('data-variant')).toBe('danger');
    expect(card?.textContent).toContain('App\\Models\\User');
    expect(card?.textContent).toContain('posts');
    expect(card?.textContent).toContain('12×');
});

it('spells out the eager-load fix for the relation', () => {
    const { container } = render(NPlusOneCard, { props: { entry: finding() } });

    expect(container.textContent).toContain("->with('posts')");
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(NPlusOneCard, { props: { entry: finding() } });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[7]]);
});
