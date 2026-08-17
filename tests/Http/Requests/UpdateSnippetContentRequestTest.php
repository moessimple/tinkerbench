<?php

declare(strict_types=1);

use App\Http\Requests\UpdateSnippetContentRequest;
use Illuminate\Validation\Rule;

it('uses the right validation rules', function (): void {
    expect(new UpdateSnippetContentRequest()->rules())->toEqual([
        'content' => ['required', Rule::string()->max(100_000)],
    ]);
});

it('exposes the content input as a string', function (): void {
    $request = new UpdateSnippetContentRequest();
    $request->merge(['content' => "echo 'hi';"]);

    expect($request->content())->toBe("echo 'hi';");
});
