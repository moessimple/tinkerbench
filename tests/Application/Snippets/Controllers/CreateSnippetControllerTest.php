<?php

declare(strict_types=1);

use Application\Snippets\Controllers\CreateSnippetController;
use Application\Snippets\Requests\SnippetNameRequest;
use Illuminate\Foundation\Http\Middleware\HandlePrecognitiveRequests;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('uses the right request', function (): void {
    expect(CreateSnippetController::class)->toUseFormRequest(SnippetNameRequest::class);
});

it('uses the right middleware', function (): void {
    expect(CreateSnippetController::class)->toUseMiddleware(HandlePrecognitiveRequests::class);
});

it('creates a new snippet with default content', function (): void {
    $this->postJson('/api/snippets', ['name' => 'my-new-snippet'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    Storage::disk('snippets')->assertExists('my-new-snippet.php', "echo 'Hello, world!';");
});

it('leaves an already existing snippet untouched', function (): void {
    Storage::disk('snippets')->put('scratch.php', 'echo "kept";');

    $this->postJson('/api/snippets', ['name' => 'scratch'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    Storage::disk('snippets')->assertExists('scratch.php', 'echo "kept";');
});
