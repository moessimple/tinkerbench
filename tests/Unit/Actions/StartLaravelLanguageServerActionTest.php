<?php

declare(strict_types=1);

use App\Actions\StartLaravelLanguageServerAction;
use App\Support\Herd;
use App\Support\LanguageServer\LaravelLspBridge;
use Mockery\MockInterface;

it('starts the laravel lsp bridge for the given project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPathOrFail')->once()->with('other-project')->andReturn('/path/to/other-project');
        $mock->shouldReceive('currentProject')->once()->andReturn('tinkerbench');
        $mock->shouldReceive('phpBinary')->once()->with('tinkerbench')->andReturn('/path/to/tinkerbench/php');
        $mock->shouldReceive('phpBinary')->once()->with('other-project')->andReturn('/path/to/other-project/php');
    });
    $this->mock(LaravelLspBridge::class, function (MockInterface $mock): void {
        $mock->shouldReceive('start')->once()->with('/path/to/other-project', '/path/to/tinkerbench/php', '/path/to/other-project/php')->andReturn(54213);
    });

    $port = resolve(StartLaravelLanguageServerAction::class)->execute('other-project');

    expect($port)->toBe(54213);
});

it('propagates the failure when the given project is unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPathOrFail')->once()->with('unknown')->andThrow(new RuntimeException('Unknown Herd project: unknown'));
    });

    resolve(StartLaravelLanguageServerAction::class)->execute('unknown');
})->throws(RuntimeException::class, 'Unknown Herd project: unknown');
