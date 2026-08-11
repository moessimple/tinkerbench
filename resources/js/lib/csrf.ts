export function xsrfHeader(): Record<string, string> {
    const match = /(?:^|; )XSRF-TOKEN=([^;]*)/.exec(document.cookie);

    return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {};
}
