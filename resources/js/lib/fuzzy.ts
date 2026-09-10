/**
 * Fuzzy subsequence match with light ranking. Every character of `query` must occur in `name` in
 * order; a contiguous run, and a match at the start or right after a word boundary (`-`, `_`, `/`,
 * `.`, or a space), each score higher, so the closest name gets the highest score. Returns `null`
 * when `name` is not a subsequence match at all. `query` is expected to be lowercase already;
 * `rankByFuzzyMatch()` guarantees that.
 */
export function fuzzyScore(name: string, query: string): number | null {
    const haystack = name.toLowerCase();
    let score = 0;
    let queryIndex = 0;
    let previousMatchIndex = -2;

    for (let i = 0; i < haystack.length && queryIndex < query.length; i++) {
        if (haystack[i] !== query[queryIndex]) {
            continue;
        }

        if (i === previousMatchIndex + 1) {
            score += 4;
        }

        if (i === 0 || /[-_/. ]/.test(haystack[i - 1])) {
            score += 3;
        }

        score += 1;
        previousMatchIndex = i;
        queryIndex++;
    }

    return queryIndex === query.length ? score : null;
}

/**
 * Orders `candidates` best match first for `query`, dropping the ones that don't match. An empty
 * query keeps the original order. Ties break alphabetically. The query is lowercased here, so
 * callers don't have to.
 */
export function rankByFuzzyMatch(
    candidates: string[],
    query: string,
): string[] {
    if (query === '') {
        return candidates;
    }

    const normalizedQuery = query.toLowerCase();

    return candidates
        .map((name) => ({ name, score: fuzzyScore(name, normalizedQuery) }))
        .filter((scored): scored is { name: string; score: number } => {
            return scored.score !== null;
        })
        .sort((a, b) => b.score - a.score || a.name.localeCompare(b.name))
        .map((scored) => scored.name);
}
