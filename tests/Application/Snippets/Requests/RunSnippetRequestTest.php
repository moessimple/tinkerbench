<?php

declare(strict_types=1);

use Application\Snippets\Requests\RunSnippetRequest;
use Illuminate\Validation\Rule;

it('uses the right validation rules', function (): void {
    expect(new RunSnippetRequest()->rules())->toEqual([
        'code' => ['required', Rule::string()->max(100000)],
    ]);
});

it('exposes the code input as a string', function (): void {
    $request = new RunSnippetRequest();
    $request->merge(['code' => "echo 'hi';"]);

    expect($request->code())->toBe("echo 'hi';");
});
