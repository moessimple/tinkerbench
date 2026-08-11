<?php

declare(strict_types=1);

use Application\Snippets\Requests\RunSnippetRequest;

test('authorizes every request', function (): void {
    expect(new RunSnippetRequest()->authorize())->toBeTrue();
});

test('requires code as a string', function (): void {
    expect(new RunSnippetRequest()->rules())->toBe([
        'code' => ['required', 'string'],
    ]);
});

test('exposes the code input as a string', function (): void {
    $request = new RunSnippetRequest();
    $request->merge(['code' => "echo 'hi';"]);

    expect($request->code())->toBe("echo 'hi';");
});
