import { expect, it } from 'vitest';
import { fuzzyScore, rankByFuzzyMatch } from './fuzzy';

it('scores a name whose characters appear in order', () => {
    expect(fuzzyScore('scratch', 'srh')).not.toBeNull();
});

it('returns null when the characters are present but out of order', () => {
    expect(fuzzyScore('scratch', 'hrs')).toBeNull();
});

it('returns null when a character is missing entirely', () => {
    expect(fuzzyScore('scratch', 'scz')).toBeNull();
});

it('scores a contiguous run higher than the same characters scattered', () => {
    const contiguous = fuzzyScore('abcxx', 'abc');
    const scattered = fuzzyScore('axbxc', 'abc');

    expect(contiguous).toBeGreaterThan(scattered as number);
});

it('scores a match right after a word boundary higher than one mid-word', () => {
    const afterBoundary = fuzzyScore('latest-run', 'run');
    const midWord = fuzzyScore('latestrun', 'run');

    expect(afterBoundary).toBeGreaterThan(midWord as number);
});

it('ranks matches best first and drops non-matches', () => {
    expect(
        rankByFuzzyMatch(['user-account-settings', 'readme'], 'uas'),
    ).toEqual(['user-account-settings']);
});

it('ranks a prefix or word-boundary match above a mid-word match', () => {
    expect(rankByFuzzyMatch(['latest-run', 'test-helpers'], 'test')).toEqual([
        'test-helpers',
        'latest-run',
    ]);
});

it('breaks score ties alphabetically', () => {
    expect(rankByFuzzyMatch(['beta', 'alpha'], 'a')).toEqual(['alpha', 'beta']);
});

it('keeps the original order for an empty query', () => {
    expect(rankByFuzzyMatch(['zebra', 'apple'], '')).toEqual([
        'zebra',
        'apple',
    ]);
});

it('matches case-insensitively', () => {
    expect(rankByFuzzyMatch(['Scratch', 'notes'], 'SCR')).toEqual(['Scratch']);
});
