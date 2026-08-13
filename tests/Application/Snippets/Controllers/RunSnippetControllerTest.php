<?php

declare(strict_types=1);

use Application\Projects\Middleware\EnsureKnownProjectMiddleware;
use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Requests\RunSnippetRequest;
use Domain\Snippets\Actions\RunSnippetAction;
use Mockery\MockInterface;
use Support\Herd;
use Support\SnippetRunResult;

it('uses the right request', function (): void {
    expect(RunSnippetController::class)->toUseFormRequest(RunSnippetRequest::class);
});

it('uses the right middleware', function (): void {
    expect(RunSnippetController::class)->toUseMiddleware(EnsureKnownProjectMiddleware::class);
});

it('uses the right action', function (): void {
    $this->mock(RunSnippetAction::class)
        ->shouldReceive('execute')->once()->with('my-project', Mockery::type('string'))->andReturn(new SnippetRunResult('output'));

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
        ->assertExactJson(['output' => '2']);
});
