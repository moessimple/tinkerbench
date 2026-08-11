<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('deletes an existing snippet', function (): void {
    Storage::disk('snippets')->put('scratch.php', 'echo 1;');

    $this->deleteJson('/api/snippets/scratch')
        ->assertOk()
        ->assertExactJson(['ok' => true]);

    Storage::disk('snippets')->assertMissing('scratch.php');
});

it('returns 404 when the snippet to delete does not exist', function (): void {
    $this->deleteJson('/api/snippets/missing')
        ->assertNotFound()
        ->assertExactJson(['ok' => false, 'error' => 'Snippet not found']);
});
