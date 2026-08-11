import { expect, it } from 'vitest';
import { isValidSnippetName } from '@/snippets';

it('accepts names made of letters, digits, underscores and dashes', () => {
    expect(isValidSnippetName('my-snippet_2')).toBe(true);
});

it('rejects an empty name', () => {
    expect(isValidSnippetName('')).toBe(false);
});

it('rejects a name containing whitespace', () => {
    expect(isValidSnippetName('my snippet')).toBe(false);
});

it('rejects a name containing a special character', () => {
    expect(isValidSnippetName('my/snippet')).toBe(false);
});
