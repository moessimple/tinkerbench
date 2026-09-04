import { format } from 'sql-formatter';

const KEYWORDS = new Set([
    'select',
    'from',
    'where',
    'and',
    'or',
    'not',
    'null',
    'is',
    'in',
    'like',
    'between',
    'exists',
    'as',
    'on',
    'using',
    'join',
    'inner',
    'left',
    'right',
    'full',
    'outer',
    'cross',
    'group',
    'order',
    'by',
    'having',
    'limit',
    'offset',
    'union',
    'all',
    'distinct',
    'count',
    'sum',
    'avg',
    'min',
    'max',
    'insert',
    'into',
    'values',
    'update',
    'set',
    'delete',
    'create',
    'table',
    'alter',
    'drop',
    'index',
    'primary',
    'key',
    'foreign',
    'references',
    'default',
    'asc',
    'desc',
    'case',
    'when',
    'then',
    'else',
    'end',
]);

function escapeHtml(text: string): string {
    return text
        .replaceAll('&', '&amp;')
        .replaceAll('<', '&lt;')
        .replaceAll('>', '&gt;');
}

/**
 * Pretty-prints a single-line statement (as produced by `QueryExecuted::toRawSql()`) across lines.
 * Returns the input unchanged when the formatter cannot parse it.
 */
export function formatSql(sql: string): string {
    try {
        return format(sql, { language: 'sql', keywordCase: 'preserve' });
    } catch {
        return sql;
    }
}

/**
 * Wraps SQL keywords, string literals and numbers in themed spans. Escapes HTML first, so the
 * result is safe to pass to `v-html`. A single pass over the token grammar keeps keywords inside
 * string literals from being highlighted.
 */
export function highlightSql(sql: string): string {
    return escapeHtml(sql).replace(
        /('(?:[^']|'')*')|(\b\d+(?:\.\d+)?\b)|([A-Za-z_][A-Za-z0-9_]*)/g,
        (match, string: string, number: string, word: string) => {
            if (string) {
                return `<span class="text-warn">${match}</span>`;
            }

            if (number) {
                return `<span class="text-accent">${match}</span>`;
            }

            return KEYWORDS.has(word.toLowerCase())
                ? `<span class="text-accent">${match}</span>`
                : match;
        },
    );
}
