<?php

declare(strict_types=1);

use Application\Snippets\Requests\UpdateSnippetNameRequest;

it('requires name to match the allowed snippet name pattern', function (): void {
    expect(new UpdateSnippetNameRequest()->rules())->toBe([
        'name' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]+$/'],
    ]);
});

it('exposes the name input as a string', function (): void {
    $request = new UpdateSnippetNameRequest();
    $request->merge(['name' => 'my-snippet']);

    expect($request->name())->toBe('my-snippet');
});

it('rejects a name with characters outside the allowed pattern', function (): void {
    createFormRequest(UpdateSnippetNameRequest::class, ['name' => 'bad name!'])
        ->assertInvalid('name');
});

it('accepts a name made of letters, digits, underscores and dashes', function (): void {
    createFormRequest(UpdateSnippetNameRequest::class, ['name' => 'my-snippet_2'])
        ->assertValid('name');
});
