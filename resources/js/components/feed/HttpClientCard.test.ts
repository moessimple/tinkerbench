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

function httpClientEntry(
    overrides: Partial<Extract<FeedItem, { kind: 'http_client' }>> = {},
): Extract<FeedItem, { kind: 'http_client' }> {
    return {
        body_preview: '{"id":1}',
        content_type: 'application/json',
        duration_ms: 42.5,
        duration_str: '42.50ms',
        kind: 'http_client',
        line: 4,
        method: 'GET',
        request_headers: { Accept: ['application/json'] },
        response_headers: { 'Content-Type': ['application/json'] },
        size: null,
        status: 200,
        truncated: false,
        url: 'https://example.test/users',
        ...overrides,
    };
}

it('shows the method, url, status and duration for a successful call', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry() },
    });

    const card = container.querySelector('[data-label="HTTP"]');
    expect(card?.getAttribute('data-variant')).toBe('default');
    expect(card?.textContent).toContain('GET');
    expect(card?.textContent).toContain('https://example.test/users');
    expect(card?.textContent).toContain('200');
    expect(card?.textContent).toContain('42.50ms');
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

it('shows the redacted request and response headers', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                request_headers: { Authorization: ['[REDACTED]'] },
                response_headers: { 'Set-Cookie': ['[REDACTED]'] },
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('Authorization');
    expect(text).toContain('[REDACTED]');
    expect(text).toContain('Set-Cookie');
});

it('shows the body preview and a truncated hint when the body was cut', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                body_preview: 'partial body...',
                truncated: true,
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('partial body...');
    expect(text.toLowerCase()).toContain('truncated');
});

it('shows content type and size instead of a preview for a non-textual body', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                body_preview: null,
                content_type: 'application/octet-stream',
                size: 1024,
            }),
        },
    });

    const text = container.textContent ?? '';
    expect(text).toContain('application/octet-stream');
    expect(text).toContain('1024');
});

it('falls back to a generic label when the content type itself is missing', () => {
    const { container } = render(HttpClientCard, {
        props: {
            entry: httpClientEntry({
                body_preview: null,
                content_type: null,
                size: 512,
            }),
        },
    });

    expect(container.textContent).toContain('unknown content type');
});

it('puts the body preview on the clipboard copy button', () => {
    const { container } = render(HttpClientCard, {
        props: { entry: httpClientEntry({ body_preview: '{"id":1}' }) },
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
