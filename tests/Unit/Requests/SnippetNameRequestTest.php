<?php

declare(strict_types=1);

use App\Http\Requests\SnippetNameRequest;
use Illuminate\Validation\Rule;

it('uses the right validation rules', function (): void {
    expect(new SnippetNameRequest()->rules())->toEqual([
        'name' => ['required', Rule::string()->alphaDash(true)->max(200)],
    ]);
});

it('exposes the name input as a string', function (): void {
    $request = new SnippetNameRequest();
    $request->merge(['name' => 'my-snippet']);

    expect($request->name())->toBe('my-snippet');
});

it('accepts a name made only of letters, digits, dashes and underscores', function (): void {
    createFormRequest(SnippetNameRequest::class, ['name' => 'My-Snippet_123'])
        ->assertValid();
});

it('rejects a name that attempts to traverse outside the snippets disk', function (string $name): void {
    createFormRequest(SnippetNameRequest::class, ['name' => $name])
        ->assertInvalid('name');
})->with([
    'parent directory traversal' => '../../etc/passwd',
    'absolute path' => '/etc/passwd',
    'nested path' => 'sub/snippet',
]);
