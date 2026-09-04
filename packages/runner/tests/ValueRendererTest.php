<?php

declare(strict_types=1);

use Tinkerbench\Runner\ValueRenderer;

it('renders a value as an interactive Symfony VarDumper HTML dump', function (): void {
    $html = (new ValueRenderer())->render('rendered value');

    expect($html)
        ->toContain('Sfdump(')
        ->toContain('rendered value');
});

it('renders nested values with collapsible markup', function (): void {
    $html = (new ValueRenderer())->render(['outer' => ['inner' => 'nested value']]);

    expect($html)
        ->toContain('sf-dump-compact')
        ->toContain('sf-dump-toggle')
        ->toContain('inner')
        ->toContain('nested value');
});

it('prefixes the dump with the given label', function (): void {
    $html = (new ValueRenderer())->render(42, 'the answer');

    expect($html)->toContain('the answer');
});

it('renders a value as plain text for the clipboard, without VarDumper html or scripts', function (): void {
    $text = (new ValueRenderer())->renderText(['outer' => ['inner' => 'nested value']]);

    expect($text)
        ->toContain('nested value')
        ->not->toContain('Sfdump(')
        ->not->toContain('<script');
});

it('prefixes the plain-text render with the given label', function (): void {
    expect((new ValueRenderer())->renderText(42, 'the answer'))->toContain('the answer');
});

it('truncates an oversized plain-text render so the clipboard stays bounded', function (): void {
    $text = (new ValueRenderer())->renderText(str_repeat('a', 50_000));

    expect(mb_strlen($text))->toBeLessThan(21_000)
        ->and($text)->toEndWith('...');
});
