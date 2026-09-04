import { expect, it } from 'vitest';
import { formatSql, highlightSql } from './sql';

it('breaks a single-line statement across lines', () => {
    const formatted = formatSql(
        "select * from users where email = 'a@b.com' limit 1",
    );

    expect(formatted).toContain('select\n');
    expect(formatted).toContain('\nfrom\n');
    expect(formatted).toContain('\nwhere\n');
});

it('leaves keyword casing as written', () => {
    expect(formatSql('SELECT 1')).toContain('SELECT');
    expect(formatSql('select 1')).toContain('select');
});

it('returns the input unchanged when it cannot be parsed', () => {
    expect(formatSql(')(')).toBe(')(');
});

it('wraps keywords, strings, and numbers in themed spans', () => {
    const highlighted = highlightSql('select id from users limit 10');

    expect(highlighted).toContain('<span class="text-accent">select</span>');
    expect(highlighted).toContain('<span class="text-accent">from</span>');
    expect(highlighted).toContain('<span class="text-accent">10</span>');
});

it('highlights a quoted string literal as a whole', () => {
    const highlighted = highlightSql("where email = 'john@example.com'");

    expect(highlighted).toContain(
        `<span class="text-warn">'john@example.com'</span>`,
    );
});

it('does not highlight keywords that appear inside a string literal', () => {
    const highlighted = highlightSql("where note = 'select from where'");

    expect(highlighted).toContain(
        `<span class="text-warn">'select from where'</span>`,
    );
    expect(highlighted).not.toContain(
        '<span class="text-accent">select</span>',
    );
});

it('leaves plain identifiers unstyled', () => {
    expect(highlightSql('users')).toBe('users');
});

it('escapes HTML before highlighting', () => {
    const highlighted = highlightSql("where name = '<script>'");

    expect(highlighted).not.toContain('<script>');
    expect(highlighted).toContain('&lt;script&gt;');
});
