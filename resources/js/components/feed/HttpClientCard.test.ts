import { render } from '@testing-library/vue';
import { expect, it, vi } from 'vitest';
import type { FeedItem } from '@/types';
import HttpClientCard from './HttpClientCard.vue';

// Card has its own test (Card.test.ts); stubbed so this test only proves HttpClientCard's own content.
vi.mock('../Card.vue', () => ({
    default: {
        props: ['label', 'line', 'variant', 'copy'],
        emits: ['navigate'],
        template: `<article :data-label="label" :data-line="line" :data-variant="variant" :data-copy="copy">
            <slot /><slot name="footer" />
            <button class="nav" @click="$emit('navigate', line)">nav</button>
        </article>`,
    },
}));

function rowValue(container: Element, label: string): string | null {
    const dt = Array.from(container.querySelectorAll('dt')).find(
        (el) => el.textContent?.trim() === label,
    );

    return dt?.nextElementSibling?.textContent?.trim() ?? null;
}

function httpClientEntry(
    overrides: Partial<Extract<FeedItem, { kind: 'http_client' }>> = {},
): Extract<FeedItem, { kind: 'http_client' }> {
    return {
        duration_ms: 42.5,
        duration_str: '42.50 ms',
        faked: false,
        kind: 'http_client',
        line: 4,
        method: 'GET',
        request_body_preview: '',
        request_content_type: null,
        request_headers: { Accept: ['application/json'] },
        request_size: null,
        request_truncated: false,
        request_type: 'Other',
        response_body_preview: '{"id":1}',
        response_content_type: 'application/json',
        response_headers: { 'Content-Type': ['application/json'] },
        response_size: null,
        response_truncated: false,
        status: 200,
        url: 'https://example.test/users',
        ...overrides,
    };
}

it('shows the method, url, status and duration for a successful call', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry() },
    });

    const card = container.querySelector('[data-label="HTTP"]');
    const text = card?.textContent ?? '';
    expect(card?.getAttribute('data-variant')).toBe('default');
    expect(text).toContain('GET');
    expect(text).toContain('https://example.test/users');
    expect(text).toContain('200');
    expect(text).toContain('42.50 ms');
});

it('shows a warning variant for a 4xx response', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ status: 404 }) },
    });

    expect(
        container
            .querySelector('[data-label="HTTP"]')
            ?.getAttribute('data-variant'),
    ).toBe('warning');
});

it('shows a danger variant for a 5xx response', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ status: 500 }) },
    });

    expect(
        container
            .querySelector('[data-label="HTTP"]')
            ?.getAttribute('data-variant'),
    ).toBe('danger');
});

it('reports success as true below 400 and false at or above it', () => {
    const { container: ok } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ status: 200 }) },
    });
    const { container: notOk } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ status: 404 }) },
    });

    expect(rowValue(ok, 'Success')).toBe('true');
    expect(rowValue(notOk, 'Success')).toBe('false');
});

it('reports Real Request as true for a genuine call and false for a faked one', () => {
    const { container: real } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ faked: false }) },
    });
    const { container: faked } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ faked: true }) },
    });

    expect(rowValue(real, 'Real Request')).toBe('true');
    expect(rowValue(faked, 'Real Request')).toBe('false');
});

it('shows the request type', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ request_type: 'Multipart' }) },
    });

    expect(container.textContent).toContain('Multipart');
});

it('shows the request and response headers', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                request_headers: { Authorization: ['Bearer secret-token'] },
                response_headers: { 'Set-Cookie': ['session=abc'] },
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('Authorization: Bearer secret-token');
    expect(text).toContain('Set-Cookie: session=abc');
});

it('shows "none" for an empty header set', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ request_headers: {} }) },
    });

    expect(container.textContent).toContain('none');
});

it('shows the response body preview and a truncated hint when it was cut', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                response_body_preview: 'partial body...',
                response_truncated: true,
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('partial body...');
    expect(text.toLowerCase()).toContain('truncated');
});

it('shows content type and size instead of a preview for a non-textual response body', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                response_body_preview: null,
                response_content_type: 'application/octet-stream',
                response_size: 1024,
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('application/octet-stream');
    expect(text).toContain('1024');
});

it('falls back to a generic label when the response content type itself is missing', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                response_body_preview: null,
                response_content_type: null,
                response_size: 512,
            }),
        },
    });

    expect(container.textContent).toContain('unknown content type');
});

it('shows the request body preview and a truncated hint when present', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                method: 'POST',
                request_body_preview: '{"name":"Ada"}',
                request_content_type: 'application/json',
                request_type: 'Json',
                request_truncated: true,
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('{"name":"Ada"}');
    expect(text.toLowerCase()).toContain('truncated');
});

it('shows request content type and size instead of a preview for a non-textual request body', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                request_body_preview: null,
                request_content_type: 'application/octet-stream',
                request_size: 256,
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('application/octet-stream');
    expect(text).toContain('256');
});

it('puts the response body preview on the clipboard copy button', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({ response_body_preview: '{"id":1}' }),
        },
    });

    expect(
        container
            .querySelector('[data-label="HTTP"]')
            ?.getAttribute('data-copy'),
    ).toBe('{"id":1}');
});

it('re-emits navigate with the entry line', async () => {
    const { container, emitted } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ line: 7 }) },
    });

    container.querySelector<HTMLButtonElement>('button.nav')?.click();

    expect(emitted().navigate).toEqual([[7]]);
});
