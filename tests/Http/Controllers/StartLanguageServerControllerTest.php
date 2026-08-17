<?php

declare(strict_types=1);

use App\Actions\StartLanguageServerAction;
use App\Http\Controllers\StartLanguageServerController;
use App\Http\Middleware\EnsureKnownProjectMiddleware;
use App\Support\Herd;
use App\Support\LanguageServerBridge;
use Mockery\MockInterface;

it('uses the right action', function (): void {
    $this->mock(StartLanguageServerAction::class)
        ->shouldReceive('execute')->once()->with('my-project')->andReturn(54213);

    app()->call(new StartLanguageServerController(), ['project' => 'my-project']);
});

it('uses the right middleware', function (): void {
    expect(StartLanguageServerController::class)->toUseMiddleware(EnsureKnownProjectMiddleware::class);
});

it('returns the port for a known project', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn(base_path());
        $mock->shouldReceive('phpBinary')->with('my-project')->andReturn(PHP_BINARY);
        $mock->shouldReceive('phpVersion')->with(PHP_BINARY)->andReturn('8.5.0');
    });
    $this->mock(LanguageServerBridge::class, function (MockInterface $mock): void {
        $mock->shouldReceive('start')->with(base_path(), '8.5.0')->andReturn(54213);
    });

    $this->postJson('/api/projects/my-project/language-server')
        ->assertOk()
        ->assertExactJson(['port' => 54213]);
});

it('rejects a project unknown to herd with a 404', function (): void {
    $this->mock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->once()->with('unknown-project')->andReturn(null);
    });

    $this->postJson('/api/projects/unknown-project/language-server')
        ->assertNotFound()
        ->assertJsonPath('message', 'Unknown Herd project: unknown-project');
});
