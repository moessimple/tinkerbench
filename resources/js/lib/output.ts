export type OutputResult =
    | { raw: string; type: 'dump' | 'html' | 'text' }
    | { pretty: string; raw: string; type: 'json' };

function escapeHtml(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

export function detectOutput(text: string): OutputResult {
    const trimmed = text.trim();

    if (trimmed.includes('Sfdump(')) {
        return { type: 'dump', raw: text };
    }

    try {
        const parsed: unknown = JSON.parse(trimmed);

        if (parsed !== null && typeof parsed === 'object') {
            return {
                type: 'json',
                raw: text,
                pretty: JSON.stringify(parsed, null, 2),
            };
        }
    } catch {
        // Invalid JSON can still be HTML or plain text.
    }

    if (trimmed.startsWith('<')) {
        return { type: 'html', raw: text };
    }

    return { type: 'text', raw: text };
}

export function highlightJson(pretty: string): string {
    return escapeHtml(pretty).replace(
        /("(?:\\.|[^"\\])*"\s*:?|\b(?:true|false|null)\b|-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g,
        (match) => {
            if (match.startsWith('"')) {
                return `<span class="${match.endsWith(':') ? 'text-accent' : 'text-fg'}">${match}</span>`;
            }

            if (match === 'null') {
                return `<span class="text-muted">${match}</span>`;
            }

            return `<span class="text-accent">${match}</span>`;
        },
    );
}

export function executeScripts(container: HTMLElement): void {
    container.querySelectorAll('script').forEach((oldScript) => {
        const newScript = document.createElement('script');

        Array.from(oldScript.attributes).forEach(({ name, value }) =>
            newScript.setAttribute(name, value),
        );
        newScript.textContent = oldScript.textContent;
        oldScript.replaceWith(newScript);
    });
}
