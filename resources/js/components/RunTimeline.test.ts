import { render, screen, within } from '@testing-library/vue';
import { expect, it } from 'vitest';
import type { SnippetDebugPayload } from '@/types';
import RunTimeline from './RunTimeline.vue';

function payload(
    overrides: Partial<SnippetDebugPayload> = {},
): SnippetDebugPayload {
    return {
        application_duration_ms: 200,
        application_duration_str: '200.00 ms',
        boot_duration_ms: 130,
        boot_duration_str: '130.00 ms',
        code_duration_ms: 50,
        code_duration_str: '50.00 ms',
        duplicate_query_count: 0,
        http_duration_ms: 30,
        http_duration_str: '30.00 ms',
        http_request_count: 1,
        items: [],
        peak_memory_str: '18.50 MB',
        query_count: 2,
        query_duration_ms: 120,
        query_duration_str: '120.00 ms',
        run_duration_ms: 330,
        run_duration_str: '330.00 ms',
        ...overrides,
    };
}

function rowTexts(): string[] {
    return within(screen.getByRole('list', { name: 'Timeline' }))
        .getAllByRole('listitem')
        .map((row) =>
            Array.from(row.querySelectorAll('span'))
                .filter((span) => span.children.length === 0)
                .map((span) => span.textContent?.trim() ?? '')
                .filter((text) => text !== '')
                .join(' '),
        );
}

function barWidthOf(label: string): string {
    const bar = screen
        .getByText(label, { selector: 'span' })
        .closest('li')
        ?.querySelector<HTMLElement>('[aria-hidden="true"]');

    return bar?.style.width ?? '';
}

it('splits the application time into DB, HTTP, and code with their shares', () => {
    render(RunTimeline, { props: { debug: payload(), unmeasured: [] } });

    expect(rowTexts()).toEqual([
        'DB 120.00 ms 60%',
        'HTTP 30.00 ms 15%',
        'Code 50.00 ms 25%',
    ]);
});

it('gives code the remainder of the rounded shares so they add up to 100', () => {
    render(RunTimeline, {
        props: {
            debug: payload({
                application_duration_ms: 3,
                code_duration_ms: 1,
                code_duration_str: '1.00 ms',
                http_duration_ms: 1,
                http_duration_str: '1.00 ms',
                query_duration_ms: 1,
                query_duration_str: '1.00 ms',
            }),
            unmeasured: [],
        },
    });

    expect(rowTexts()).toEqual([
        'DB 1.00 ms 33%',
        'HTTP 1.00 ms 33%',
        'Code 1.00 ms 34%',
    ]);
});

it('sizes each bar by its share of the application time', () => {
    render(RunTimeline, { props: { debug: payload(), unmeasured: [] } });

    expect(barWidthOf('DB')).toBe('60%');
    expect(barWidthOf('Code')).toBe('25%');
});

it('leaves out DB and HTTP when the run made no queries or requests', () => {
    render(RunTimeline, {
        props: {
            debug: payload({ http_request_count: 0, query_count: 0 }),
            unmeasured: [],
        },
    });

    expect(rowTexts()).toEqual(['Code 50.00 ms 25%']);
});

it('notes under code that it includes a kind whose watcher was off', () => {
    render(RunTimeline, {
        props: {
            debug: payload({ query_count: 0 }),
            unmeasured: ['query'],
        },
    });

    expect(rowTexts()).toEqual([
        'HTTP 30.00 ms 15%',
        'Code 50.00 ms 25% includes DB (not measured)',
    ]);
});

it('explains a negative code time with its negative share', () => {
    render(RunTimeline, {
        props: {
            debug: payload({
                application_duration_ms: 148,
                code_duration_ms: -2,
                code_duration_str: '-2.00 ms',
            }),
            unmeasured: [],
        },
    });

    expect(rowTexts()[2]).toBe(
        'Code -2.00 ms -1% negative, since a query inside a faked HTTP callback counts as both DB and HTTP',
    );
});

it('draws no bar for a negative code time', () => {
    render(RunTimeline, {
        props: {
            debug: payload({
                code_duration_ms: -2,
                code_duration_str: '-2.00 ms',
            }),
            unmeasured: [],
        },
    });

    expect(barWidthOf('Code')).toBe('0%');
});

it('draws no bars when the application took no measurable time', () => {
    render(RunTimeline, {
        props: {
            debug: payload({ application_duration_ms: 0 }),
            unmeasured: [],
        },
    });

    expect(barWidthOf('DB')).toBe('0%');
});

it('shows no shares when the application took no measurable time', () => {
    render(RunTimeline, {
        props: {
            debug: payload({ application_duration_ms: 0 }),
            unmeasured: [],
        },
    });

    expect(rowTexts().map((row) => row.split(' ')[3])).toEqual([
        '0%',
        '0%',
        '0%',
    ]);
});

it('adds booting and application up to the duration', () => {
    render(RunTimeline, { props: { debug: payload(), unmeasured: [] } });

    expect(screen.getByText('Booting').closest('p')?.textContent).toContain(
        '130.00 ms',
    );
    expect(screen.getByText('Application').closest('p')?.textContent).toContain(
        '200.00 ms',
    );
    expect(screen.getByText('Duration').closest('p')?.textContent).toContain(
        '330.00 ms',
    );
});

it('explains booting as context outside the shares', () => {
    render(RunTimeline, { props: { debug: payload(), unmeasured: [] } });

    expect(
        screen.getByText('Booting').closest('p')?.getAttribute('title'),
    ).toMatch(/not a web request/i);
});
