import { afterEach, expect, it } from 'vitest';
import { xsrfHeader } from '@/lib/csrf';

afterEach(() => {
    document.cookie =
        'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/';
});

it('reads and decodes the XSRF-TOKEN cookie into a header', () => {
    document.cookie = 'XSRF-TOKEN=abc%3D123';

    expect(xsrfHeader()).toEqual({ 'X-XSRF-TOKEN': 'abc=123' });
});

it('returns no header when the XSRF-TOKEN cookie is missing', () => {
    expect(xsrfHeader()).toEqual({});
});
