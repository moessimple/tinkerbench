import { render } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import OutputCard from './OutputCard.vue';

// Card has its own test (Card.test.ts); output.ts helpers are leaf/pure and run for real.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line'],
        template: `<article :data-label="label"><slot /></article>`,
    },
}));

it('renders plain stdout as text', () => {
    const { container } = render(OutputCard, {
        props: { entry: { kind: 'output', text: 'plain printed line' } },
    });

    const card = container.querySelector('[data-label="Output"]');
    expect(card?.querySelector('pre')?.textContent).toBe('plain printed line');
    expect(card?.querySelector('iframe')).toBeNull();
});

it('renders a JSON object as a highlighted block', () => {
    const { container } = render(OutputCard, {
        props: { entry: { kind: 'output', text: '{"a":1}' } },
    });

    const pre = container.querySelector('pre');
    expect(pre?.innerHTML).toContain('<span');
    expect(pre?.textContent).toContain('"a"');
});

it('renders leading-angle-bracket output in a sandboxed iframe', () => {
    const { container } = render(OutputCard, {
        props: { entry: { kind: 'output', text: '<h1>Hi</h1>' } },
    });

    const frame = container.querySelector('iframe');
    expect(frame?.getAttribute('sandbox')).toBe('allow-scripts');
    expect(frame?.getAttribute('srcdoc')).toBe('<h1>Hi</h1>');
});

it('renders a Symfony dump payload as raw html', () => {
    const { container } = render(OutputCard, {
        props: { entry: { kind: 'output', text: '<span>Sfdump("x")</span>' } },
    });

    expect(
        container.querySelector('[data-label="Output"]')?.innerHTML,
    ).toContain('<span>Sfdump("x")</span>');
    expect(container.querySelector('iframe')).toBeNull();
    expect(container.querySelector('pre')).toBeNull();
});
