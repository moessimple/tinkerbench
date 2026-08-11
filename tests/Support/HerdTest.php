<?php

declare(strict_types=1);

use Support\Herd;

test('resolves the php binary from the configured herd bin path', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);

    expect(new Herd()->phpBinary())->toBe('/tmp/herd-bin/php');
});

test('throws when the herd bin path is not configured', function (): void {
    config(['services.herd.bin' => '']);

    new Herd()->phpBinary();
})->throws(InvalidArgumentException::class);

test('runs a snippet in a subprocess and returns its output', function (): void {
    $result = new Herd()->runSnippet("return 'from the subprocess';");

    expect($result->output)->toBe('from the subprocess');
});

test('falls back to the system PHP binary when the configured herd path does not exist', function (): void {
    config(['services.herd.bin' => '/nonexistent-herd-bin']);

    $result = new Herd()->runSnippet("return 'fallback works';");

    expect($result->output)->toBe('fallback works');
});

test('two snippets that redeclare the same class both succeed', function (): void {
    $herd = new Herd();

    $first = $herd->runSnippet("class DuplicateSnippetClass {}\n\nreturn 'first';");
    $second = $herd->runSnippet("class DuplicateSnippetClass {}\n\nreturn 'second';");

    expect($first->output)->toBe('first')
        ->and($second->output)->toBe('second');
});

test('surfaces a thrown exception via the process error output', function (): void {
    $result = new Herd()->runSnippet("throw new RuntimeException('boom');");

    expect($result->output)->toContain('boom');
});
