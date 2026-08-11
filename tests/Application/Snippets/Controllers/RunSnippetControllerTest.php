<?php

declare(strict_types=1);

use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Requests\RunSnippetRequest;
use Domain\Snippets\Actions\RunSnippetAction;
use Support\HerdContract;
use Support\SnippetRunResult;

test('uses RunSnippetAction and RunSnippetRequest', function (): void {
    $herd = Mockery::mock(HerdContract::class);
    $herd->shouldReceive('runSnippet')->once()->with("echo 'hi';")->andReturn(new SnippetRunResult('hi'));

    $request = new RunSnippetRequest();
    $request->merge(['code' => "echo 'hi';"]);

    $response = new RunSnippetController()($request, new RunSnippetAction($herd));

    expect($response->getData(true))->toBe(['output' => 'hi']);
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
