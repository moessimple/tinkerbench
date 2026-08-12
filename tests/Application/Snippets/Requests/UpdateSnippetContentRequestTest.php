<?php

declare(strict_types=1);

use Application\Snippets\Requests\UpdateSnippetContentRequest;
use Illuminate\Validation\Rule;

it('requires content as a bounded string', function (): void {
    expect(new UpdateSnippetContentRequest()->rules())->toEqual([
        'content' => ['required', Rule::string()->max(100000)],
    ]);
});

it('exposes the content input as a string', function (): void {
    $request = new UpdateSnippetContentRequest();
    $request->merge(['content' => "echo 'hi';"]);

    expect($request->content())->toBe("echo 'hi';");
});
