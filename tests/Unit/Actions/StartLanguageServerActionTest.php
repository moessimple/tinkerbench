<?php

declare(strict_types=1);

use App\Actions\StartLanguageServerAction;
use App\Support\Herd;
use App\Support\LanguageServerBridge;
use Mockery\MockInterface;

it('starts the language server bridge for the given project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('other-project')->andReturn('/path/to/other-project');
        $mock->shouldReceive('phpBinary')->once()->with('other-project')->andReturn('/path/to/other-project/php');
        $mock->shouldReceive('phpVersion')->once()->with('/path/to/other-project/php')->andReturn('8.3.1');
    });
    $this->mock(LanguageServerBridge::class, function (MockInterface $mock): void {
        $mock->shouldReceive('start')->once()->with('/path/to/other-project', '8.3.1')->andReturn(54213);
    });

    $port = resolve(StartLanguageServerAction::class)->execute('other-project');

    expect($port)->toBe(54213);
});

it('throws when the given project is unknown to herd', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('unknown')->andReturn(null);
    });

    resolve(StartLanguageServerAction::class)->execute('unknown');
})->throws(RuntimeException::class);
