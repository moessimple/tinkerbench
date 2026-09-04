import { render } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import ResultCard from './ResultCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves ResultCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant', 'copy'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-copy="copy"><slot /></article>`,
    },
}));

it('renders the rendered return value under a Result card', () => {
    const { container } = render(ResultCard, {
        props: {
            entry: {
                html: '<i>the value</i>',
                kind: 'result',
                text: 'the value',
            },
        },
    });

    const card = container.querySelector('[data-label="Result"]');
    expect(card?.innerHTML).toContain('<i>the value</i>');
});

it('passes no line to the card, since a return value has no single source line', () => {
    const { container } = render(ResultCard, {
        props: { entry: { html: '<i>x</i>', kind: 'result', text: 'x' } },
    });

    expect(
        container
            .querySelector('[data-label="Result"]')
            ?.hasAttribute('data-line'),
    ).toBe(false);
});

it('hands Card the plain-text form of the value for copying', () => {
    const { container } = render(ResultCard, {
        props: {
            entry: {
                html: '<i>x</i>',
                kind: 'result',
                text: "array:1 [ 'a' => 1 ]",
            },
        },
    });

    expect(
        container
            .querySelector('[data-label="Result"]')
            ?.getAttribute('data-copy'),
    ).toBe("array:1 [ 'a' => 1 ]");
});
