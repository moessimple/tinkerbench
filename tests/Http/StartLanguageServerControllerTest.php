<?php

declare(strict_types=1);

use App\Actions\StartLanguageServerAction;
use App\Http\Controllers\StartLanguageServerController;
use App\Http\Middleware\EnsureKnownProject;

it('uses the right action', function (): void {
    $this->mock(StartLanguageServerAction::class)
        ->shouldReceive('execute')->once()->with('my-project')->andReturn(54213);

    app()->call(new StartLanguageServerController(), ['project' => 'my-project']);
});

it('uses the right middleware', function (): void {
    expect(StartLanguageServerController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('returns the port for a known project', function (): void {
    mockKnownProject();

    $this->mock(StartLanguageServerAction::class)->shouldReceive('execute')->andReturn(54213);

    $this->postJson('/api/projects/my-project/language-server')
        ->assertOk()
        ->assertExactJson(['port' => 54213]);
});
