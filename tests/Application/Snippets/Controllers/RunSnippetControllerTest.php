<?php

declare(strict_types=1);

use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Requests\RunSnippetRequest;
use Domain\Snippets\Actions\RunSnippetAction;
use Support\SnippetRunResult;

it('uses the right request', function (): void {
    expect(RunSnippetController::class)->toUseFormRequest(RunSnippetRequest::class);
});

it('uses the right action', function (): void {
    $this->mock(RunSnippetAction::class)
        ->shouldReceive('execute')->once()->with(Mockery::type('string'))->andReturn(new SnippetRunResult('output'));

    $request = new RunSnippetRequest();
    $request->merge(['code' => 'echo 1;']);

    app()->call(new RunSnippetController(), ['request' => $request]);
});

it('returns the right output', function (): void {
    $this->postJson('/snippets/executions', ['code' => 'echo 1 + 1;'])
        ->assertOk()
        ->assertExactJson(['output' => '2']);
});
