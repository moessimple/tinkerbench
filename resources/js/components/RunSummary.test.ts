import { render, screen } from '@testing-library/vue';
import { expect, it } from 'vitest';
import type { SnippetDebugPayload } from '@/types';
import RunSummary from './RunSummary.vue';

const debug: SnippetDebugPayload = {
    application_duration_ms: 20,
    application_duration_str: '20.00 ms',
    boot_duration_ms: 180,
    boot_duration_str: '180.00 ms',
    code_duration_ms: 20,
    code_duration_str: '20.00 ms',
    duplicate_query_count: 0,
    http_duration_ms: 0,
    http_duration_str: '0 μs',
    http_request_count: 0,
    items: [],
    peak_memory_str: '18.50 MB',
    query_count: 0,
    query_duration_ms: 0,
    query_duration_str: '0 μs',
    run_duration_ms: 200,
    run_duration_str: '200.00 ms',
};

it('shows the labelled duration and memory usage', () => {
    render(RunSummary, { props: { debug } });

    const summary = screen.getByRole('region', { name: 'Run time' });

    const text = summary.textContent?.replace(/\s+/g, ' ');

    expect(text).toContain('Duration 200.00 ms');
    expect(text).toContain('Memory Usage 18.50 MB');
});
