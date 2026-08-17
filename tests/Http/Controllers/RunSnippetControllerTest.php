<?php

declare(strict_types=1);

use App\Actions\RunSnippetAction;
use App\Http\Controllers\RunSnippetController;
use App\Http\Middleware\EnsureKnownProject;
use App\Http\Requests\RunSnippetRequest;
use App\Support\Herd;
use App\Support\SnippetRunResult;
use Mockery\MockInterface;

it('uses the right request', function (): void {
    expect(RunSnippetController::class)->toUseFormRequest(RunSnippetRequest::class);
});

it('uses the right middleware', function (): void {
    expect(RunSnippetController::class)->toUseMiddleware(EnsureKnownProject::class);
});

it('uses the right action', function (): void {
    $this->mock(RunSnippetAction::class)
        ->shouldReceive('execute')->once()->with('my-project', Mockery::type('string'))->andReturn(new SnippetRunResult('output', null));

    $request = new RunSnippetRequest();
    $request->merge(['code' => 'echo 1;']);

    app()->call(new RunSnippetController(), ['request' => $request, 'project' => 'my-project']);
});

it('returns the right output', function (): void {
    // A partial mock, not a full one: projectPath()/phpBinary() are stubbed since "my-project"
    // isn't a real Herd site, but runSnippet() itself still runs for real, this test's whole
    // point is proving a real subprocess execution produces the right output.
    $this->partialMock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn(base_path());
        $mock->shouldReceive('phpBinary')->with('my-project')->andReturn(PHP_BINARY);
    });

    $this->postJson('/projects/my-project/snippets/executions', ['code' => "<?php\n\necho 1 + 1;"])
        ->assertOk()
        ->assertJson(['output' => '2']);
});

it('returns the debug data from the result', function (): void {
    $this->partialMock(Herd::class, function (MockInterface $mock): void {
        $mock->shouldReceive('projectPath')->with('my-project')->andReturn(base_path());
    });
    $this->mock(RunSnippetAction::class)
        ->shouldReceive('execute')->once()->andReturn(new SnippetRunResult('output', ['queries' => ['count' => 1]]));

    $this->postJson('/projects/my-project/snippets/executions', ['code' => 'echo 1;'])
        ->assertOk()
        ->assertExactJson(['output' => 'output', 'debug' => ['queries' => ['count' => 1]]]);
});
