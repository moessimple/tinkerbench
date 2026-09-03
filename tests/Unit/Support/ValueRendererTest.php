<?php

declare(strict_types=1);

use App\Support\ValueRenderer;

it('renders a value as an interactive Symfony VarDumper HTML dump', function (): void {
    $html = new ValueRenderer()->render('rendered value');

    expect($html)
        ->toContain('Sfdump(')
        ->toContain('rendered value');
});

it('renders nested values with collapsible markup', function (): void {
    $html = new ValueRenderer()->render(['outer' => ['inner' => 'nested value']]);

    expect($html)
        ->toContain('sf-dump-compact')
        ->toContain('sf-dump-toggle')
        ->toContain('inner')
        ->toContain('nested value');
});

it('prefixes the dump with the given label', function (): void {
    $html = new ValueRenderer()->render(42, 'the answer');

    expect($html)->toContain('the answer');
});
