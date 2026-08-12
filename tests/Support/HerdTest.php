<?php

declare(strict_types=1);

use Support\Herd;

it('resolves the php binary from the configured herd bin path', function (): void {
    config(['services.herd.bin' => '/tmp/herd-bin']);

    expect(new Herd()->phpBinary())->toBe('/tmp/herd-bin/php');
});

it('throws when the herd bin path is not configured', function (): void {
    config(['services.herd.bin' => '']);

    new Herd()->phpBinary();
})->throws(InvalidArgumentException::class);

it('surfaces the process error when the configured php binary does not exist', function (): void {
    config(['services.herd.bin' => '/nonexistent-herd-bin']);

    $result = new Herd()->runSnippet("return 'unreachable';");

    expect($result->output)->not->toBe('');
});

it('runs a snippet in a subprocess and returns its output', function (): void {
    $result = new Herd()->runSnippet("return 'from the subprocess';");

    expect($result->output)->toBe('from the subprocess');
});

it('boots the application so snippets can use Laravel helpers', function (): void {
    $result = new Herd()->runSnippet("return config('app.name');");

    expect($result->output)->toBe(config('app.name'));
});

it('lets two snippets that redeclare the same class both succeed', function (): void {
    $herd = new Herd();

    $first = $herd->runSnippet("class DuplicateSnippetClass {}\n\nreturn 'first';");
    $second = $herd->runSnippet("class DuplicateSnippetClass {}\n\nreturn 'second';");

    expect($first->output)->toBe('first')
        ->and($second->output)->toBe('second');
});

it('surfaces a thrown exception via the process error output', function (): void {
    $result = new Herd()->runSnippet("throw new RuntimeException('boom');");

    expect($result->output)->toContain('boom');
});

it('keeps output printed before an uncaught exception instead of discarding it', function (): void {
    $result = new Herd()->runSnippet("echo 'partial output'; throw new RuntimeException('boom');");

    expect($result->output)->toContain('partial output')
        ->and($result->output)->toContain('boom');
});

it('does not duplicate an opening tag the snippet already provides', function (): void {
    $result = new Herd()->runSnippet("<?php\n\nreturn 'already tagged';");

    expect($result->output)->toBe('already tagged');
});

it('cleans up the temp snippet file after running', function (): void {
    $before = glob(sys_get_temp_dir().'/tinkerbench-snippet-*.php');

    new Herd()->runSnippet("return 'cleanup check';");

    $after = glob(sys_get_temp_dir().'/tinkerbench-snippet-*.php');

    expect($after)->toBe($before);
});
