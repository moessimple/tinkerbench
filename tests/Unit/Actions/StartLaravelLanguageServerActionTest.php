<?php

declare(strict_types=1);

use App\Actions\StartLaravelLanguageServerAction;
use App\Support\Herd;
use App\Support\LaravelLspBridge;
use Mockery\MockInterface;

it('starts the laravel lsp bridge for the given project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('other-project')->andReturn('/path/to/other-project');
    });
    $this->mock(LaravelLspBridge::class, function (MockInterface $mock): void {
        $mock->shouldReceive('start')->once()->with('/path/to/other-project')->andReturn(54213);
    });

    $port = resolve(StartLaravelLanguageServerAction::class)->execute('other-project');

    expect($port)->toBe(54213);
});

it('throws when the given project is unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('unknown')->andReturn(null);
    });

    resolve(StartLaravelLanguageServerAction::class)->execute('unknown');
})->throws(RuntimeException::class);
