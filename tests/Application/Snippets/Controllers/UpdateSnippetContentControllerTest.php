<?php

declare(strict_types=1);

use Application\Snippets\Controllers\UpdateSnippetContentController;
use Application\Snippets\Requests\UpdateSnippetContentRequest;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('uses the right request', function (): void {
    expect(UpdateSnippetContentController::class)->toUseFormRequest(UpdateSnippetContentRequest::class);
});

it('persists the given content for the named snippet', function (): void {
    $this->putJson('/api/snippets/scratch', ['content' => 'echo "saved";'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    Storage::disk('snippets')->assertExists('scratch.php', 'echo "saved";');
});
