import { fireEvent, render, screen } from '@testing-library/vue';
import { beforeEach, expect, it, vi } from 'vitest';
import Card from './Card.vue';

const writeText = vi.fn();

beforeEach(() => {
    writeText.mockReset().mockResolvedValue(undefined);
    Object.defineProperty(navigator, 'clipboard', {
        value: { writeText },
        configurable: true,
    });
});

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

    expect(screen.queryByRole('button', { name: /line/i })).toBeNull();
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

it('offers no copy button when no copy string is given', () => {
    render(Card, {
        props: { label: 'Dump', line: null },
        slots: { default: 'body' },
    });

    expect(screen.queryByRole('button', { name: /copy/i })).toBeNull();
});

it('copies the copy string to the clipboard on click', async () => {
    render(Card, {
        props: { label: 'Query', line: null, copy: 'select 1' },
    });

    await fireEvent.click(screen.getByRole('button', { name: /copy/i }));

    expect(writeText).toHaveBeenCalledWith('select 1');
});

it('confirms the copy by relabelling the button', async () => {
    render(Card, {
        props: { label: 'Query', line: null, copy: 'select 1' },
    });

    await fireEvent.click(screen.getByRole('button', { name: /copy/i }));

    expect(await screen.findByRole('button', { name: /copied/i })).toBeTruthy();
});
