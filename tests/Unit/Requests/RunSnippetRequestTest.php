<?php

declare(strict_types=1);

use App\Http\Requests\RunSnippetRequest;
use Illuminate\Validation\Rule;

it('uses the right validation rules', function (): void {
    expect(new RunSnippetRequest()->rules())->toEqual([
        'code' => ['required', Rule::string()->max(100_000)],
        'enabled_watchers' => ['sometimes', 'array'],
        'enabled_watchers.*' => [Rule::in(['view'])],
    ]);
});

it('exposes the code input as a string', function (): void {
    $request = new RunSnippetRequest();
    $request->merge(['code' => "echo 'hi';"]);

    expect($request->code())->toBe("echo 'hi';");
});

it('exposes the enabled watchers as an array', function (): void {
    $request = new RunSnippetRequest();
    $request->merge(['enabled_watchers' => ['view']]);

    expect($request->enabledWatchers())->toBe(['view']);
});

it('exposes no enabled watchers when none were given', function (): void {
    expect(new RunSnippetRequest()->enabledWatchers())->toBe([]);
});
