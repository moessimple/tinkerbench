<?php

declare(strict_types=1);

use Application\Snippets\Controllers\UpdateSnippetNameController;
use Application\Snippets\Requests\UpdateSnippetNameRequest;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('uses the right request', function (): void {
    expect(UpdateSnippetNameController::class)->toUseFormRequest(UpdateSnippetNameRequest::class);
});

it('renames an existing snippet to an unused name', function (): void {
    Storage::disk('snippets')->put('old.php', 'echo 1;');

    $this->patchJson('/api/snippets/old', ['name' => 'new'])
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    Storage::disk('snippets')->assertMissing('old.php');
    Storage::disk('snippets')->assertExists('new.php', 'echo 1;');
});

it('returns 404 when the snippet to rename does not exist', function (): void {
    $this->patchJson('/api/snippets/missing', ['name' => 'new'])
        ->assertNotFound()
        ->assertExactJson(['ok' => false, 'error' => 'Snippet not found']);
});

it('returns 409 when the target name is already taken', function (): void {
    Storage::disk('snippets')->put('old.php', 'echo 1;');
    Storage::disk('snippets')->put('new.php', 'echo 2;');

    $this->patchJson('/api/snippets/old', ['name' => 'new'])
        ->assertStatus(409)
        ->assertExactJson(['ok' => false, 'error' => 'A snippet named "new" already exists']);
});
