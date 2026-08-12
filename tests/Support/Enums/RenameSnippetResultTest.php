<?php

declare(strict_types=1);

use Support\Enums\RenameSnippetResult;

it('has exactly the expected rename outcomes', function (): void {
    expect(RenameSnippetResult::cases())->toBe([
        RenameSnippetResult::Renamed,
        RenameSnippetResult::Missing,
        RenameSnippetResult::Conflict,
    ]);
});
