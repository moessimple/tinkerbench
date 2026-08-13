import { render, screen } from '@testing-library/vue';
import { expect, it } from 'vitest';
import DebugPanel from '@/components/DebugPanel.vue';
import type { SnippetDebugPayload } from '@/types';

function renderPanel(debug: SnippetDebugPayload) {
    return render(DebugPanel, { props: { debug } });
}

it('shows no environment section', () => {
    renderPanel({});

    expect(screen.queryByText('Environment')).toBeNull();
});

it('shows a table of the queries a snippet ran', () => {
    renderPanel({
        queries: {
            count: 1,
            statements: [
                {
                    sql: 'select * from users where id = ?',
                    params: [1],
                    duration_str: '1.14ms',
                    connection: 'sqlite',
                },
            ],
        },
    });

    const table = screen.getByRole('table', { name: 'Queries' });
    expect(table.textContent).toContain('select * from users where id = ?');
    expect(table.textContent).toContain('1');
    expect(table.textContent).toContain('1.14ms');
});

it('hides the queries section when no query ran', () => {
    renderPanel({ queries: { count: 0, statements: [] } });

    expect(screen.queryByRole('table', { name: 'Queries' })).toBeNull();
});

it('shows a timing summary for each measured segment', () => {
    renderPanel({
        time: {
            duration_str: '61.92ms',
            measures: [{ label: 'snippet', duration_str: '3ms' }],
        },
    });

    expect(screen.getByText('snippet')).toBeTruthy();
    expect(screen.getByText('3ms')).toBeTruthy();
});

it('shows every dumped value with its label', () => {
    renderPanel({
        messages: {
            count: 2,
            messages: [
                { label: '1', message: 'first' },
                { label: '2', message: 'second' },
            ],
        },
    });

    expect(screen.getByText('first')).toBeTruthy();
    expect(screen.getByText('second')).toBeTruthy();
});

it('hides the dumps section when nothing was dumped', () => {
    renderPanel({ messages: { count: 0, messages: [] } });

    expect(screen.queryByText('Dumps')).toBeNull();
});

it('shows a caught exception with its origin', () => {
    renderPanel({
        exceptions: {
            count: 1,
            exceptions: [
                {
                    type: 'RuntimeException',
                    message: 'boom',
                    file: 'snippet.php',
                    line: 3,
                },
            ],
        },
    });

    expect(screen.getByText(/RuntimeException/)).toBeTruthy();
    expect(screen.getByText(/boom/)).toBeTruthy();
    expect(screen.getByText('snippet.php:3')).toBeTruthy();
});

it('hides the exceptions section when nothing was caught', () => {
    renderPanel({ exceptions: { count: 0, exceptions: [] } });

    expect(screen.queryByText('Exceptions')).toBeNull();
});
