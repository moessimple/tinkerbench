<?php

declare(strict_types=1);

use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Requests\RunSnippetRequest;
use Domain\Snippets\Actions\RunSnippetAction;
use Support\HerdContract;
use Support\SnippetRunResult;

test('uses RunSnippetAction', function (): void {
    $herd = Mockery::mock(HerdContract::class);
    $herd->shouldReceive('runSnippet')->once()->andReturn(new SnippetRunResult('from the action'));

    $request = new RunSnippetRequest();
    $request->merge(['code' => 'anything']);

    $response = app()->call(new RunSnippetController(), [
        'request' => $request,
        'runSnippet' => new RunSnippetAction($herd),
    ]);

    expect($response->getData(true))->toBe(['output' => 'from the action']);
});

test('uses RunSnippetRequest', function (): void {
    $herd = Mockery::mock(HerdContract::class);
    $herd->shouldReceive('runSnippet')->once()->with('the submitted code')->andReturn(new SnippetRunResult('irrelevant'));

    $request = new RunSnippetRequest();
    $request->merge(['code' => 'the submitted code']);

    app()->call(new RunSnippetController(), [
        'request' => $request,
        'runSnippet' => new RunSnippetAction($herd),
    ]);
});

test('runs the posted code isolated and returns its output as json', function (): void {
    $this->postJson('/snippets/executions', ['code' => 'echo 1 + 1;'])
        ->assertOk()
        ->assertExactJson(['output' => '2']);
});

test('requires code', function (): void {
    $this->postJson('/snippets/executions', [])
        ->assertInvalid(['code']);
});

test('requires code to be a string', function (): void {
    $this->postJson('/snippets/executions', ['code' => 123])
        ->assertInvalid(['code']);
});
