<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Storage;
use Support\Enums\RenameSnippetResult;
use Support\SnippetRepository;

beforeEach(function (): void {
    Storage::fake('snippets');
});

it('lists existing snippet names sorted alphabetically', function (): void {
    Storage::disk('snippets')->put('my-project/zebra.php', 'echo 1;');
    Storage::disk('snippets')->put('my-project/apple.php', 'echo 2;');

    expect(new SnippetRepository()->names('my-project'))->toBe(['apple', 'zebra']);
});

it('returns no names when no snippets exist', function (): void {
    expect(new SnippetRepository()->names('my-project'))->toBe([]);
});

it("keeps two projects' snippet lists independent", function (): void {
    Storage::disk('snippets')->put('project-a/shared-name.php', 'echo "a";');
    Storage::disk('snippets')->put('project-b/shared-name.php', 'echo "b";');
    Storage::disk('snippets')->put('project-b/only-in-b.php', 'echo "b only";');

    expect(new SnippetRepository()->names('project-a'))->toBe(['shared-name'])
        ->and(new SnippetRepository()->names('project-b'))->toBe(['only-in-b', 'shared-name']);
});

it('creates a snippet with default content when it does not exist yet', function (): void {
    new SnippetRepository()->ensureExists('scratch');

    Storage::disk('snippets')->assertExists('scratch.php', "<?php\n\necho 'Hello, world!';");
});

it('leaves an existing snippet untouched', function (): void {
    Storage::disk('snippets')->put('scratch.php', 'echo "kept";');

    new SnippetRepository()->ensureExists('scratch');

    Storage::disk('snippets')->assertExists('scratch.php', 'echo "kept";');
});

it('returns the contents of an existing snippet', function (): void {
    Storage::disk('snippets')->put('scratch.php', 'echo "hi";');

    expect(new SnippetRepository()->contents('scratch'))->toBe('echo "hi";');
});

it('throws when reading the contents of a missing snippet', function (): void {
    new SnippetRepository()->contents('missing');
})->throws(RuntimeException::class, 'The snippet at missing.php is missing.');

it('writes the given content to a snippet', function (): void {
    new SnippetRepository()->write('scratch', 'echo "written";');

    Storage::disk('snippets')->assertExists('scratch.php', 'echo "written";');
});

it('renames an existing snippet to an unused name', function (): void {
    Storage::disk('snippets')->put('old.php', 'echo "content";');

    $result = new SnippetRepository()->rename('old', 'new');

    expect($result)->toBe(RenameSnippetResult::Renamed);
    Storage::disk('snippets')->assertMissing('old.php');
    Storage::disk('snippets')->assertExists('new.php', 'echo "content";');
});

it('reports a missing source snippet when renaming', function (): void {
    $result = new SnippetRepository()->rename('missing', 'new');

    expect($result)->toBe(RenameSnippetResult::Missing);
});

it('reports a conflict when the target snippet name is already taken', function (): void {
    Storage::disk('snippets')->put('old.php', 'echo 1;');
    Storage::disk('snippets')->put('new.php', 'echo 2;');

    $result = new SnippetRepository()->rename('old', 'new');

    expect($result)->toBe(RenameSnippetResult::Conflict);
    Storage::disk('snippets')->assertExists('old.php', 'echo 1;');
});

it('deletes an existing snippet', function (): void {
    Storage::disk('snippets')->put('scratch.php', 'echo 1;');

    expect(new SnippetRepository()->delete('scratch'))->toBeTrue();
    Storage::disk('snippets')->assertMissing('scratch.php');
});

it('reports that a missing snippet was not deleted', function (): void {
    expect(new SnippetRepository()->delete('missing'))->toBeFalse();
});

it('resolves the absolute path of a snippet', function (): void {
    $path = new SnippetRepository()->path('scratch');

    expect($path)->toBe(Storage::disk('snippets')->path('scratch.php'));
});
