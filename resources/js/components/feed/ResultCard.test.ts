import { render } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import ResultCard from './ResultCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves ResultCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line"><slot /></article>`,
    },
}));

it('renders the rendered return value under a Result card', () => {
    const { container } = render(ResultCard, {
        props: { entry: { html: '<i>the value</i>', kind: 'result' } },
    });

    const card = container.querySelector('[data-label="Result"]');
    expect(card?.innerHTML).toContain('<i>the value</i>');
});

it('passes no line to the card, since a return value has no single source line', () => {
    const { container } = render(ResultCard, {
        props: { entry: { html: '<i>x</i>', kind: 'result' } },
    });

    expect(
        container
            .querySelector('[data-label="Result"]')
            ?.hasAttribute('data-line'),
    ).toBe(false);
});
