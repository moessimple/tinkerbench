<?php

declare(strict_types=1);

use Application\Snippets\Requests\UpdateSnippetContentRequest;

it('requires content as a bounded string', function (): void {
    expect(new UpdateSnippetContentRequest()->rules())->toBe([
        'content' => ['required', 'string', 'max:100000'],
    ]);
});

it('exposes the content input as a string', function (): void {
    $request = new UpdateSnippetContentRequest();
    $request->merge(['content' => "echo 'hi';"]);

    expect($request->content())->toBe("echo 'hi';");
});

it('rejects a missing content field', function (): void {
    dispatchFormRequest(UpdateSnippetContentRequest::class, [])
        ->assertInvalid('content');
});

it('accepts a well-formed content field', function (): void {
    dispatchFormRequest(UpdateSnippetContentRequest::class, ['content' => "echo 'hi';"])
        ->assertValid('content');
});
