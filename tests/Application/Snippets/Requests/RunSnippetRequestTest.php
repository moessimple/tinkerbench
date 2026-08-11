<?php

declare(strict_types=1);

use Application\Snippets\Requests\RunSnippetRequest;

it('requires code as a string', function (): void {
    expect(new RunSnippetRequest()->rules())->toBe([
        'code' => ['required', 'string'],
    ]);
});

it('exposes the code input as a string', function (): void {
    $request = new RunSnippetRequest();
    $request->merge(['code' => "echo 'hi';"]);

    expect($request->code())->toBe("echo 'hi';");
});
