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
        duration_ms: 12.3,
        duration_str: '12.30ms',
        http_duration_ms: 0,
        http_duration_str: '0.00ms',
        http_request_count: 0,
        items: [],
        php_duration_ms: 12.3,
        php_duration_str: '12.30ms',
        peak_memory_str: '18.50 MB',
        query_count: 0,
        query_duration_ms: 0,
        query_duration_str: '0.00ms',
        run_duration_ms: 192.3,
        run_duration_str: '192.30ms',
        ...overrides,
    };
}

it('shows the snippet duration and peak memory', () => {
    render(RunSummary, { props: { debug: payload() } });

    screen.getByText('12.30ms');
    screen.getByText('18.50 MB');
});

it('shows the query time with the query and duplicate counts', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                duplicate_query_count: 3,
                query_count: 12,
                query_duration_str: '8.20ms',
            }),
        },
    });

    screen.getByText('DB 8.20ms (12 queries, 3 duplicate)');
});

it('leaves out the duplicate count when no query repeated', () => {
    render(RunSummary, {
        props: {
            debug: payload({ query_count: 2, query_duration_str: '1.00ms' }),
        },
    });

    screen.getByText('DB 1.00ms (2 queries)');
});

it('shows the http time with the request count', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                http_duration_str: '5.00ms',
                http_request_count: 2,
            }),
        },
    });

    screen.getByText('HTTP 5.00ms (2 requests)');
});

it('uses the singular for a single query and a single request', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                http_duration_str: '5.00ms',
                http_request_count: 1,
                query_count: 1,
                query_duration_str: '1.00ms',
            }),
        },
    });

    screen.getByText('DB 1.00ms (1 query)');
    screen.getByText('HTTP 5.00ms (1 request)');
});

it('shows the other time once the run made a query or an http call', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                php_duration_str: '3.10ms',
                query_count: 1,
                query_duration_str: '9.20ms',
            }),
        },
    });

    screen.getByText('other 3.10ms');
});

it('shows no breakdown when the run made no queries or http calls', () => {
    render(RunSummary, { props: { debug: payload() } });

    expect(screen.queryByText(/^DB /)).toBeNull();
    expect(screen.queryByText(/^HTTP /)).toBeNull();
    expect(screen.queryByText(/^other /)).toBeNull();
});

it('explains what each part of the breakdown measures', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                http_duration_str: '5.00ms',
                http_request_count: 1,
                query_count: 1,
                query_duration_str: '1.00ms',
            }),
        },
    });

    expect(
        screen.getByText('DB 1.00ms (1 query)').getAttribute('title'),
    ).toMatch(/sum of the query cards/i);
    expect(
        screen.getByText('HTTP 5.00ms (1 request)').getAttribute('title'),
    ).toMatch(/sum of the http cards/i);
    expect(screen.getByText('other 12.30ms').getAttribute('title')).toMatch(
        /snippet time minus db and http/i,
    );
});

it('shows the boot time of the target before the snippet duration', () => {
    render(RunSummary, { props: { debug: payload() } });

    screen.getByText('boot 180.00ms');
});

it('explains that the boot time is not comparable to a web request', () => {
    render(RunSummary, { props: { debug: payload() } });

    expect(screen.getByText('boot 180.00ms').getAttribute('title')).toMatch(
        /not comparable to a web request/i,
    );
});

it('shows the run as boot plus snippet time in milliseconds on the duration', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                boot_duration_ms: 180,
                duration_ms: 1234.56,
                duration_str: '1.23s',
                run_duration_ms: 1414.56,
            }),
        },
    });

    expect(screen.getByText('1.23s').getAttribute('title')).toBe(
        '1414.56ms run = 180.00ms boot + 1234.56ms snippet',
    );
});

it('adds the snippet breakdown to the calculation once the run made a query or an http call', () => {
    render(RunSummary, {
        props: {
            debug: payload({
                duration_ms: 42.1,
                duration_str: '42.10ms',
                http_duration_ms: 5,
                http_duration_str: '5.00ms',
                http_request_count: 1,
                php_duration_ms: 5.9,
                query_count: 12,
                query_duration_ms: 31.2,
                query_duration_str: '31.20ms',
                run_duration_ms: 222.1,
            }),
        },
    });

    expect(screen.getByText('42.10ms').getAttribute('title')).toBe(
        '222.10ms run = 180.00ms boot + 42.10ms snippet\n42.10ms snippet = 31.20ms DB + 5.00ms HTTP + 5.90ms other',
    );
});
