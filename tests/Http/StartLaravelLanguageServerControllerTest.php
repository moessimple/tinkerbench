<?php

declare(strict_types=1);

use App\Actions\StartLaravelLanguageServerAction;
use App\Http\Controllers\StartLaravelLanguageServerController;
use App\Http\Middleware\EnsureKnownProject;

it('uses the right action', function (): void {
    $this->mock(StartLaravelLanguageServerAction::class)
        ->shouldReceive('execute')->once()->with('my-project')->andReturn(54213);

    app()->call(new StartLaravelLanguageServerController(), ['project' => 'my-project']);
});

it('uses the right middleware', function (): void {
    expect(StartLaravelLanguageServerController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('returns the port for a known project', function (): void {
    mockKnownProject();

    $this->mock(StartLaravelLanguageServerAction::class)->shouldReceive('execute')->andReturn(54213);

    $this->postJson('/api/projects/my-project/laravel-language-server')
        ->assertOk()
        ->assertExactJson(['port' => 54213]);
});
