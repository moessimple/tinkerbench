<?php

declare(strict_types=1);

use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Validation\Rule;

it('requires name as a bounded, alpha-dash string', function (): void {
    expect(new SnippetNameRequest()->rules())->toEqual([
        'name' => ['required', Rule::string()->alphaDash(true)->max(200)],
    ]);
});

it('exposes the name input as a string', function (): void {
    $request = new SnippetNameRequest();
    $request->merge(['name' => 'my-snippet']);

    expect($request->name())->toBe('my-snippet');
});
