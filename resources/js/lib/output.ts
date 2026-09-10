export type OutputResult =
    | { raw: string; type: 'html' | 'text' }
    | { pretty: string; raw: string; type: 'json' };

function escapeHtml(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

/**
 * Classifies a snippet's raw process stdout for rendering. Raw stdout is untrusted: it can carry
 * data the snippet did not author (`echo $model->bio`), so it is never rendered as trusted HTML.
 * A `'json'` result is escaped before highlighting, `'text'` is shown verbatim in a <pre>, and
 * `'html'` is rendered only inside a sandboxed <iframe> (see OutputCard.vue) so its scripts cannot
 * reach tinkerbench's own page. VarDumper HTML printed straight to stdout lands in the `'html'`
 * branch too; the interactive dump path is the structural DumpCard/ResultCard, not this one.
 */
export function detectOutput(text: string): OutputResult {
    const trimmed = text.trim();

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
