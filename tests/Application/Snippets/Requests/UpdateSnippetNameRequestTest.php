<?php

declare(strict_types=1);

use Application\Snippets\Requests\UpdateSnippetNameRequest;
use Illuminate\Support\Facades\Validator;

it('requires name to match the allowed snippet name pattern', function (): void {
    expect(new UpdateSnippetNameRequest()->rules())->toBe([
        'name' => ['required', 'string', 'max:200', 'regex:/\A[A-Za-z0-9_-]+\z/'],
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

it('rejects a name with a trailing newline', function (): void {
    // Tested directly against the rule, not through createFormRequest(): the app's
    // global TrimStrings middleware already strips a trailing newline from any real
    // request before validation runs, which would hide a regression here. Anchored
    // with \A/\z rather than ^/$, because PCRE's $ also matches just before a
    // trailing newline, which would otherwise let "name\n" pass this rule.
    $validator = Validator::make(['name' => "my-snippet\n"], new UpdateSnippetNameRequest()->rules());

    expect($validator->fails())->toBeTrue();
});

it('rejects a name longer than 200 characters', function (): void {
    createFormRequest(UpdateSnippetNameRequest::class, ['name' => str_repeat('a', 201)])
        ->assertInvalid('name');
});
