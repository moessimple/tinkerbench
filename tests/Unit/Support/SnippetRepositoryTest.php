<?php

declare(strict_types=1);

use App\Enums\DeleteSnippetResult;
use App\Enums\Disk;
use App\Enums\RenameSnippetResult;
use App\Support\SnippetRepository;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;

beforeEach(function (): void {
    Storage::fake(Disk::Snippets);
});

it('lists existing snippet names sorted alphabetically', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/zebra.php', 'echo 1;');
    Storage::disk(Disk::Snippets)->put('my-project/apple.php', 'echo 2;');

    expect(new SnippetRepository()->names('my-project'))->toBe(['apple', 'zebra']);
});

it('returns no names when no snippets exist', function (): void {
    expect(new SnippetRepository()->names('my-project'))->toBe([]);
});

it("keeps two projects' snippet lists independent", function (): void {
    Storage::disk(Disk::Snippets)->put('project-a/shared-name.php', 'echo "a";');
    Storage::disk(Disk::Snippets)->put('project-b/shared-name.php', 'echo "b";');
    Storage::disk(Disk::Snippets)->put('project-b/only-in-b.php', 'echo "b only";');

    expect(new SnippetRepository()->names('project-a'))->toBe(['shared-name'])
        ->and(new SnippetRepository()->names('project-b'))->toBe(['only-in-b', 'shared-name']);
});

it('creates a snippet with default content when it does not exist yet', function (): void {
    expect(new SnippetRepository()->ensureExists('my-project', 'scratch'))->toBeTrue();

    Storage::disk(Disk::Snippets)->assertExists('my-project/scratch.php', File::get(resource_path('stubs/scratch.php')));
});

it('reports failure instead of success when creating a missing snippet fails', function (): void {
    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturn(false);
            $mock->shouldReceive('put')->once()->andReturn(false);
        }));

    expect(new SnippetRepository()->ensureExists('my-project', 'scratch'))->toBeFalse();
});

it('leaves an existing snippet untouched', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/scratch.php', 'echo "kept";');

    expect(new SnippetRepository()->ensureExists('my-project', 'scratch'))->toBeTrue();

    Storage::disk(Disk::Snippets)->assertExists('my-project/scratch.php', 'echo "kept";');
});

it('lets the same snippet name coexist independently in two projects', function (): void {
    Storage::disk(Disk::Snippets)->put('project-a/scratch.php', 'echo "a";');

    new SnippetRepository()->ensureExists('project-b', 'scratch');

    expect(new SnippetRepository()->contents('project-a', 'scratch'))->toBe('echo "a";')
        ->and(new SnippetRepository()->contents('project-b', 'scratch'))->toBe(File::get(resource_path('stubs/scratch.php')));
});

it('returns the contents of an existing snippet', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/scratch.php', 'echo "hi";');

    expect(new SnippetRepository()->contents('my-project', 'scratch'))->toBe('echo "hi";');
});

it('throws when reading the contents of a missing snippet', function (): void {
    new SnippetRepository()->contents('my-project', 'missing');
})->throws(RuntimeException::class, 'The snippet at my-project/missing.php is missing.');

it('writes the given content to a snippet', function (): void {
    expect(new SnippetRepository()->write('my-project', 'scratch', 'echo "written";'))->toBeTrue();

    Storage::disk(Disk::Snippets)->assertExists('my-project/scratch.php', 'echo "written";');
});

it('reports failure instead of success when writing a snippet fails', function (): void {
    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('put')->once()->andReturn(false);
        }));

    expect(new SnippetRepository()->write('my-project', 'scratch', 'echo "written";'))->toBeFalse();
});

it('logs the failure when writing a snippet fails', function (): void {
    Log::shouldReceive('error')->once()->with('Unable to write the snippet at my-project/scratch.php.');

    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('put')->once()->andReturn(false);
        }));

    new SnippetRepository()->write('my-project', 'scratch', 'echo "written";');
});

it('renames an existing snippet to an unused name', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/old.php', 'echo "content";');

    $result = new SnippetRepository()->rename('my-project', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Renamed);
    Storage::disk(Disk::Snippets)->assertMissing('my-project/old.php');
    Storage::disk(Disk::Snippets)->assertExists('my-project/new.php', 'echo "content";');
});

it('locks the target snippet name for the duration of the rename', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/old.php', 'echo "content";');

    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->with(5);
    $lock->shouldReceive('release')->once();

    Cache::shouldReceive('lock')
        ->once()
        ->with('tinkerbench:snippet-rename:my-project:new', 5)
        ->andReturn($lock);

    $result = new SnippetRepository()->rename('my-project', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Renamed);
});

it('releases the rename lock even when the source snippet is missing', function (): void {
    $lock = Mockery::mock(Lock::class);
    $lock->shouldReceive('block')->once()->with(5);
    $lock->shouldReceive('release')->once();

    Cache::shouldReceive('lock')->once()->andReturn($lock);

    new SnippetRepository()->rename('my-project', 'missing', 'new');
});

it('reports a missing source snippet when renaming', function (): void {
    $result = new SnippetRepository()->rename('my-project', 'missing', 'new');

    expect($result)->toBe(RenameSnippetResult::Missing);
});

it('reports a conflict when the target snippet name is already taken', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/old.php', 'echo 1;');
    Storage::disk(Disk::Snippets)->put('my-project/new.php', 'echo 2;');

    $result = new SnippetRepository()->rename('my-project', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Conflict);
    Storage::disk(Disk::Snippets)->assertExists('my-project/old.php', 'echo 1;');
});

it('reports failure instead of success when renaming a snippet fails', function (): void {
    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->with('my-project/old.php')->andReturn(true);
            $mock->shouldReceive('exists')->with('my-project/new.php')->andReturn(false);
            $mock->shouldReceive('move')->once()->andReturn(false);
        }));

    $result = new SnippetRepository()->rename('my-project', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Failed);
});

it('logs the failure when renaming a snippet fails', function (): void {
    Log::shouldReceive('error')->once()->with('Unable to rename the snippet at my-project/old.php to my-project/new.php.');

    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->with('my-project/old.php')->andReturn(true);
            $mock->shouldReceive('exists')->with('my-project/new.php')->andReturn(false);
            $mock->shouldReceive('move')->once()->andReturn(false);
        }));

    new SnippetRepository()->rename('my-project', 'old', 'new');
});

it('does not conflict with a same-named snippet in a different project when renaming', function (): void {
    Storage::disk(Disk::Snippets)->put('project-a/old.php', 'echo "a";');
    Storage::disk(Disk::Snippets)->put('project-b/new.php', 'echo "b";');

    $result = new SnippetRepository()->rename('project-a', 'old', 'new');

    expect($result)->toBe(RenameSnippetResult::Renamed);
    Storage::disk(Disk::Snippets)->assertExists('project-a/new.php', 'echo "a";');
});

it('deletes an existing snippet', function (): void {
    Storage::disk(Disk::Snippets)->put('my-project/scratch.php', 'echo 1;');

    expect(new SnippetRepository()->delete('my-project', 'scratch'))->toBe(DeleteSnippetResult::Deleted);
    Storage::disk(Disk::Snippets)->assertMissing('my-project/scratch.php');
});

it('reports a missing snippet when deleting', function (): void {
    expect(new SnippetRepository()->delete('my-project', 'missing'))->toBe(DeleteSnippetResult::Missing);
});

it('reports failure instead of success when deleting a snippet fails', function (): void {
    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('delete')->once()->andReturn(false);
        }));

    expect(new SnippetRepository()->delete('my-project', 'scratch'))->toBe(DeleteSnippetResult::Failed);
});

it('logs the failure when deleting a snippet fails', function (): void {
    Log::shouldReceive('error')->once()->with('Unable to delete the snippet at my-project/scratch.php.');

    Storage::shouldReceive('disk')
        ->with(Disk::Snippets)
        ->andReturn(Mockery::mock(Filesystem::class, function (MockInterface $mock): void {
            $mock->shouldReceive('exists')->andReturn(true);
            $mock->shouldReceive('delete')->once()->andReturn(false);
        }));

    new SnippetRepository()->delete('my-project', 'scratch');
});
