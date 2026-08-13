import { expect, it } from 'vitest';
import { detectOutput, executeScripts, highlightJson } from './output';

it('detects an Symfony VarDumper HTML dump', () => {
    const text = '<script>Sfdump("sf-dump-1")</script>';

    expect(detectOutput(text)).toEqual({ type: 'dump', raw: text });
});

it('detects a JSON object and pretty-prints it', () => {
    const text = '{"name":"tinkerbench"}';

    expect(detectOutput(text)).toEqual({
        type: 'json',
        raw: text,
        pretty: '{\n  "name": "tinkerbench"\n}',
    });
});

it('does not treat a JSON scalar as structured JSON', () => {
    expect(detectOutput('"just a string"')).toEqual({
        type: 'text',
        raw: '"just a string"',
    });
});

it('does not treat invalid JSON as structured JSON', () => {
    expect(detectOutput('{not valid json')).toEqual({
        type: 'text',
        raw: '{not valid json',
    });
});

it('detects HTML output', () => {
    const text = '<h1>Hello</h1>';

    expect(detectOutput(text)).toEqual({ type: 'html', raw: text });
});

it('falls back to plain text for anything else', () => {
    expect(detectOutput('hello world')).toEqual({
        type: 'text',
        raw: 'hello world',
    });
});

it('escapes HTML in the pretty JSON before highlighting it', () => {
    const highlighted = highlightJson(
        '{\n  "name": "<script>unsafe()</script>"\n}',
    );

    expect(highlighted).not.toContain('<script>unsafe()</script>');
    expect(highlighted).toContain('&lt;script&gt;unsafe()&lt;/script&gt;');
});

it('wraps JSON keys, strings, and literals in highlight spans', () => {
    const highlighted = highlightJson(
        '{\n  "active": true,\n  "count": 1,\n  "label": null\n}',
    );

    expect(highlighted).toContain('<span class="text-accent">"active":</span>');
    expect(highlighted).toContain('<span class="text-accent">true</span>');
    expect(highlighted).toContain('<span class="text-accent">1</span>');
    expect(highlighted).toContain('<span class="text-muted">null</span>');
});

it('replaces script tags with fresh nodes so browsers re-run them', () => {
    const container = document.createElement('div');
    container.innerHTML = '<script data-marker="1">console.log(1);</script>';
    const original = container.querySelector('script');

    executeScripts(container);

    const replaced = container.querySelector('script');
    expect(replaced).not.toBe(original);
    expect(replaced?.getAttribute('data-marker')).toBe('1');
    expect(replaced?.textContent).toBe('console.log(1);');
});
