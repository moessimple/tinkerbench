import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import DumpCard from './DumpCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves DumpCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line">
            <slot />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

it('renders the dumped html under a Dump card at its line', () => {
    const { container } = render(DumpCard, {
        props: { entry: { html: '<i>dumped</i>', kind: 'dump', line: 5 } },
    });

    const card = container.querySelector('[data-label="Dump"]');
    expect(card?.getAttribute('data-line')).toBe('5');
    expect(card?.innerHTML).toContain('<i>dumped</i>');
});

it('re-emits navigate with the entry line', async () => {
    const { emitted } = render(DumpCard, {
        props: { entry: { html: '<i>x</i>', kind: 'dump', line: 9 } },
    });

    await fireEvent.click(screen.getByText('nav'));

    expect(emitted().navigate).toEqual([[9]]);
});
