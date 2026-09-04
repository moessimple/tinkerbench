import { fireEvent, render, screen } from '@testing-library/vue';
import { expect, it } from 'vitest';
import Card from './Card.vue';

it('renders the label', () => {
    render(Card, { props: { label: 'Query', line: 3 } });

    expect(screen.getByText('Query')).toBeTruthy();
});

it('renders the line as a button and emits navigate with the line on click', async () => {
    const { emitted } = render(Card, { props: { label: 'Dump', line: 12 } });

    await fireEvent.click(screen.getByRole('button', { name: /line 12/i }));

    expect(emitted().navigate).toEqual([[12]]);
});

it('omits the line control entirely when line is null', () => {
    render(Card, { props: { label: 'Dump', line: null } });

    expect(screen.queryByRole('button')).toBeNull();
    expect(screen.queryByText(/line/i)).toBeNull();
});

it('marks the card with its variant for danger styling', () => {
    const { container } = render(Card, {
        props: { label: 'Exception', line: null, variant: 'danger' },
    });

    expect(container.querySelector('[data-variant="danger"]')).not.toBeNull();
});

it('marks the card with its variant for warning styling', () => {
    const { container } = render(Card, {
        props: { label: 'N+1', line: null, variant: 'warning' },
    });

    expect(container.querySelector('[data-variant="warning"]')).not.toBeNull();
});

it('renders body content passed to the default slot', () => {
    render(Card, {
        props: { label: 'Dump', line: null },
        slots: { default: '<p>body text</p>' },
    });

    expect(screen.getByText('body text')).toBeTruthy();
});
