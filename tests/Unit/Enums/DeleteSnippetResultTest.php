<?php

declare(strict_types=1);

use App\Enums\DeleteSnippetResult;

it('has exactly the expected delete outcomes', function (): void {
    expect(DeleteSnippetResult::cases())->toBe([
        DeleteSnippetResult::Deleted,
        DeleteSnippetResult::Missing,
        DeleteSnippetResult::Failed,
    ]);
});
