<?php

declare(strict_types=1);

use App\Enums\CreateSnippetResult;

it('has exactly the expected create outcomes', function (): void {
    expect(CreateSnippetResult::cases())->toBe([
        CreateSnippetResult::Created,
        CreateSnippetResult::Conflict,
        CreateSnippetResult::Failed,
    ]);
});
