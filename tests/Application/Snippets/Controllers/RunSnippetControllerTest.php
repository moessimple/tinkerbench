<?php

declare(strict_types=1);

use Application\Snippets\Controllers\RunSnippetController;
use Application\Snippets\Requests\RunSnippetRequest;

test('invokes RunSnippetAction and returns its output as json', function (): void {
    $request = new RunSnippetRequest();
    $request->merge(['code' => "echo 'hi';"]);

    $response = resolve(RunSnippetController::class)($request);

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
