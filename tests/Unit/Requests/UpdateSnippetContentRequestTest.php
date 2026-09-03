<?php

declare(strict_types=1);

use App\Http\Requests\UpdateSnippetContentRequest;
use Illuminate\Validation\Rule;

it('uses the right validation rules', function (): void {
    expect(new UpdateSnippetContentRequest()->rules())->toEqual([
        'content' => ['present', 'nullable', Rule::string()->max(100_000)],
    ]);
});

it('exposes the content input as a string', function (): void {
    $request = new UpdateSnippetContentRequest();
    $request->merge(['content' => "echo 'hi';"]);

    expect($request->content())->toBe("echo 'hi';");
});

it('exposes null content as an empty string', function (): void {
    $request = new UpdateSnippetContentRequest();
    $request->merge(['content' => null]);

    expect($request->content())->toBe('');
});

it('accepts a cleared editor, whose empty content arrives as null', function (): void {
    createFormRequest(UpdateSnippetContentRequest::class, ['content' => ''])
        ->assertValid();
});

it('rejects a request that omits the content field entirely', function (): void {
    createFormRequest(UpdateSnippetContentRequest::class, [])
        ->assertInvalid('content');
});
