<?php

declare(strict_types=1);

use Illuminate\Support\Facades\File;
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
    new SnippetRepository()->ensureExists('my-project', 'scratch');

    Storage::disk('snippets')->assertExists('my-project/scratch.php', File::get(resource_path('stubs/scratch.php')));
});

it('leaves an existing snippet untouched', function (): void {
    Storage::disk('snippets')->put('my-project/scratch.php', 'echo "kept";');

    new SnippetRepository()->ensureExists('my-project', 'scratch');

    Storage::disk('snippets')->assertExists('my-project/scratch.php', 'echo "kept";');
});

it('lets the same snippet name coexist independently in two projects', function (): void {
    Storage::disk('snippets')->put('project-a/scratch.php', 'echo "a";');

    new SnippetRepository()->ensureExists('project-b', 'scratch');

    expect(new SnippetRepository()->contents('project-a', 'scratch'))->toBe('echo "a";')
        ->and(new SnippetRepository()->contents('project-b', 'scratch'))->toBe(File::get(resource_path('stubs/scratch.php')));
});

it('returns the contents of an existing snippet', function (): void {
    Storage::disk('snippets')->put('my-project/scratch.php', 'echo "hi";');

    expect(new SnippetRepository()->contents('my-project', 'scratch'))->toBe('echo "hi";');
});

it('throws when reading the contents of a missing snippet', function (): void {
    new SnippetRepository()->contents('my-project', 'missing');
})->throws(RuntimeException::class, 'The snippet at my-project/missing.php is missing.');

it('writes the given content to a snippet', function (): void {
    new SnippetRepository()->write('my-project', 'scratch', 'echo "written";');

    Storage::disk('snippets')->assertExists('my-project/scratch.php', 'echo "written";');
});

it('renames an existing snippet to an unused name', function (): void {
    Storage::disk('snippets')->put('my-project/old.php', 'echo "content";');

    $result = new SnippetRepository()->rename('my-project', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Renamed);
    Storage::disk('snippets')->assertMissing('my-project/old.php');
    Storage::disk('snippets')->assertExists('my-project/new.php', 'echo "content";');
});

it('reports a missing source snippet when renaming', function (): void {
    $result = new SnippetRepository()->rename('my-project', 'missing', 'new');

    expect($result)->toBe(RenameSnippetResult::Missing);
});

it('reports a conflict when the target snippet name is already taken', function (): void {
    Storage::disk('snippets')->put('my-project/old.php', 'echo 1;');
    Storage::disk('snippets')->put('my-project/new.php', 'echo 2;');

    $result = new SnippetRepository()->rename('my-project', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Conflict);
    Storage::disk('snippets')->assertExists('my-project/old.php', 'echo 1;');
});

it('does not conflict with a same-named snippet in a different project when renaming', function (): void {
    Storage::disk('snippets')->put('project-a/old.php', 'echo "a";');
    Storage::disk('snippets')->put('project-b/new.php', 'echo "b";');

    $result = new SnippetRepository()->rename('project-a', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Renamed);
    Storage::disk('snippets')->assertExists('project-a/new.php', 'echo "a";');
});

it('deletes an existing snippet', function (): void {
    Storage::disk('snippets')->put('my-project/scratch.php', 'echo 1;');

    expect(new SnippetRepository()->delete('my-project', 'scratch'))->toBeTrue();
    Storage::disk('snippets')->assertMissing('my-project/scratch.php');
});

it('reports that a missing snippet was not deleted', function (): void {
    expect(new SnippetRepository()->delete('my-project', 'missing'))->toBeFalse();
});

it('resolves the absolute path of a snippet', function (): void {
    $path = new SnippetRepository()->path('my-project', 'scratch');

    expect($path)->toBe(Storage::disk('snippets')->path('my-project/scratch.php'));
});
