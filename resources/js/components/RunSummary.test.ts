import { render, screen } from '@testing-library/vue';
import { expect, it } from 'vitest';
import type { SnippetDebugPayload } from '@/types';
import RunSummary from './RunSummary.vue';

function payload(
    overrides: Partial<SnippetDebugPayload> = {},
): SnippetDebugPayload {
    return {
        boot_duration_ms: 180,
        boot_duration_str: '180.00ms',
        duplicate_query_count: 0,
        duration_ms: 20,
        duration_str: '20.00ms',
        http_duration_ms: 0,
        http_duration_str: '0.00ms',
        http_request_count: 0,
        items: [],
        peak_memory_str: '18.50 MB',
        php_duration_ms: 20,
        php_duration_str: '20.00ms',
        query_count: 0,
        query_duration_ms: 0,
        query_duration_str: '0.00ms',
        run_duration_ms: 200,
        run_duration_str: '200.00ms',
        ...overrides,
    };
}

function withBreakdown(
    overrides: Partial<SnippetDebugPayload> = {},
): SnippetDebugPayload {
    return payload({
        duration_ms: 100,
        duration_str: '100.00ms',
        http_duration_ms: 30,
        http_duration_str: '30.00ms',
        http_request_count: 1,
        php_duration_ms: 60,
        php_duration_str: '60.00ms',
        query_count: 2,
        query_duration_ms: 10,
        query_duration_str: '10.00ms',
        run_duration_ms: 280,
        run_duration_str: '280.00ms',
        ...overrides,
    });
}

function barWidths(): string[] {
    return Array.from(
        screen.getByRole('img').children,
        (segment) => (segment as HTMLElement).style.width,
    );
}

it('shows the run time and the peak memory', () => {
    render(RunSummary, { props: { debug: payload() } });

    screen.getByText('200.00ms');
    screen.getByText('18.50 MB');
});

it('lists only the boot and PHP time when the run made no queries or http calls', () => {
    render(RunSummary, { props: { debug: payload() } });

    screen.getByText('boot 180.00ms');
    screen.getByText('PHP 20.00ms');
    expect(screen.queryByText(/^DB /)).toBeNull();
    expect(screen.queryByText(/^HTTP /)).toBeNull();
});

it('splits the run into boot, DB, HTTP, and PHP time', () => {
    render(RunSummary, { props: { debug: withBreakdown() } });

    screen.getByText('boot 180.00ms');
    screen.getByText('DB 10.00ms (2 queries)');
    screen.getByText('HTTP 30.00ms (1 request)');
    screen.getByText('PHP 60.00ms');
});

it('leaves out the HTTP part when the run made only queries', () => {
    render(RunSummary, {
        props: {
            debug: withBreakdown({
                http_duration_ms: 0,
                http_duration_str: '0.00ms',
                http_request_count: 0,
            }),
        },
    });

    expect(screen.queryByText(/^HTTP /)).toBeNull();
});

it('adds the duplicate count to the DB part when a query repeated', () => {
    render(RunSummary, {
        props: {
            debug: withBreakdown({ duplicate_query_count: 3, query_count: 12 }),
        },
    });

    screen.getByText('DB 10.00ms (12 queries, 3 duplicate)');
});

it('uses the singular for a single query and a single request', () => {
    render(RunSummary, { props: { debug: withBreakdown({ query_count: 1 }) } });

    screen.getByText('DB 10.00ms (1 query)');
    screen.getByText('HTTP 30.00ms (1 request)');
});

it('describes the share of each part of the run for assistive technology', () => {
    render(RunSummary, { props: { debug: withBreakdown() } });

    screen.getByRole('img', {
        name: 'boot 64.3%, DB 3.6%, HTTP 10.7%, PHP 21.4% of the run',
    });
});

it('sizes each bar segment by its share of the run', () => {
    render(RunSummary, { props: { debug: withBreakdown() } });

    expect(barWidths()).toEqual([
        '64.2857%',
        '3.5714%',
        '10.7143%',
        '21.4286%',
    ]);
});

it('draws no bar segment for a negative PHP time while still listing its value', () => {
    render(RunSummary, {
        props: {
            debug: withBreakdown({
                php_duration_ms: -2,
                php_duration_str: '-2.00ms',
            }),
        },
    });

    expect(barWidths()[3]).toBe('0%');
    screen.getByText('PHP -2.00ms');
});

it('draws empty bar segments for a run with no measured time', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                boot_duration_ms: 0,
                duration_ms: 0,
                run_duration_ms: 0,
            }),
        },
    });

    expect(barWidths()).toEqual(['0%', '0%']);
});

it('marks the boot time as not comparable to a web request', () => {
    render(RunSummary, { props: { debug: payload() } });

    expect(
        screen.getByText('boot 180.00ms').closest('li')?.getAttribute('title'),
    ).toMatch(/not a web request/i);
});
