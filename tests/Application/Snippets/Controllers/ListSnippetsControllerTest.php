<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('returns no snippet names when none exist', function (): void {
    $this->getJson('/api/snippets')
        ->assertOk()
        ->assertExactJson([]);
});

it('returns all existing snippet names sorted alphabetically', function (): void {
    Storage::disk('snippets')->put('zebra.php', 'echo 1;');
    Storage::disk('snippets')->put('apple.php', 'echo 2;');

    $this->getJson('/api/snippets')
        ->assertOk()
        ->assertExactJson(['apple', 'zebra']);
});
