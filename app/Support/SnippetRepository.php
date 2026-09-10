<?php

declare(strict_types=1);

namespace App\Support;

use App\Enums\CreateSnippetResult;
use App\Enums\DeleteSnippetResult;
use App\Enums\Disk;
use App\Enums\RenameSnippetResult;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

class SnippetRepository
{
    /** @return list<string> */
    public function names(string $project): array
    {
        $names = [];

        foreach (Storage::disk(Disk::Snippets)->files($project) as $file) {
            if (Str::endsWith($file, '.php')) {
                $names[] = Str::of($file)->afterLast('/')->beforeLast('.php')->toString();
            }
        }

        sort($names);

        return $names;
    }

    /**
     * Idempotent open-or-create: succeeds whether the snippet already existed or was just created.
     * For the explicit "create a new snippet" action that must reject an existing name, use create().
     */
    public function ensureExists(string $project, string $snippet): bool
    {
        if ($this->exists($project, $snippet)) {
            return true;
        }

        return $this->write($project, $snippet, $this->defaultContent());
    }

    public function create(string $project, string $snippet): CreateSnippetResult
    {
        return $this->withSnippetLock(
            $project,
            $snippet,
            fn (): CreateSnippetResult => $this->createWhileLocked($project, $snippet),
        );
    }

    public function contents(string $project, string $snippet): string
    {
        return $this->read($this->relativePath($project, $snippet));
    }

    public function exists(string $project, string $snippet): bool
    {
        return Storage::disk(Disk::Snippets)->exists($this->relativePath($project, $snippet));
    }

    /**
     * Overwrites the snippet's content, creating the file if it does not exist. Deliberately not
     * serialized by withSnippetLock(): a content autosave that races a delete/rename is guarded at
     * the controller with an existence check, and the worst case is a resurrected scratch file.
     */
    public function write(string $project, string $snippet, string $contents): bool
    {
        $path = $this->relativePath($project, $snippet);
        $saved = (bool) Storage::disk(Disk::Snippets)->put($path, $contents);

        if (! $saved) {
            logger()->error("Unable to write the snippet at {$path}.");
        }

        return $saved;
    }

    public function rename(string $project, string $from, string $to): RenameSnippetResult
    {
        // Locks only the target name. A concurrent create()/rename()/delete() aimed at the same
        // target is what corrupts (two writers passing the "does it exist?" check, then clobbering
        // each other); a race on the source name instead resolves to a clean Failed result, since
        // the second move() finds nothing to move.
        return $this->withSnippetLock(
            $project,
            $to,
            fn (): RenameSnippetResult => $this->renameWhileLocked($project, $from, $to),
        );
    }

    public function delete(string $project, string $snippet): DeleteSnippetResult
    {
        return $this->withSnippetLock(
            $project,
            $snippet,
            fn (): DeleteSnippetResult => $this->deleteWhileLocked($project, $snippet),
        );
    }

    /**
     * Serializes every operation that creates, moves, or removes the snippet file at
     * "{project}/{snippet}.php" against every other such operation on the same path. The key names
     * the target resource, not the operation, so a concurrent create() and rename() both aiming at
     * the same name cannot each pass their existence check and then overwrite one another.
     *
     * @template TResult
     *
     * @param  Closure(): TResult  $callback
     * @return TResult
     */
    private function withSnippetLock(string $project, string $snippet, Closure $callback): mixed
    {
        $lock = Cache::lock("tinkerbench:snippet:{$project}:{$snippet}", 5);
        $lock->block(5);

        try {
            return $callback();
        } finally {
            $lock->release();
        }
    }

    private function deleteWhileLocked(string $project, string $snippet): DeleteSnippetResult
    {
        if (! $this->exists($project, $snippet)) {
            return DeleteSnippetResult::Missing;
        }

        $path = $this->relativePath($project, $snippet);

        if (! Storage::disk(Disk::Snippets)->delete($path)) {
            logger()->error("Unable to delete the snippet at {$path}.");

            return DeleteSnippetResult::Failed;
        }

        return DeleteSnippetResult::Deleted;
    }

    private function createWhileLocked(string $project, string $snippet): CreateSnippetResult
    {
        if ($this->exists($project, $snippet)) {
            return CreateSnippetResult::Conflict;
        }

        if (! $this->write($project, $snippet, $this->defaultContent())) {
            return CreateSnippetResult::Failed;
        }

        return CreateSnippetResult::Created;
    }

    private function renameWhileLocked(string $project, string $from, string $to): RenameSnippetResult
    {
        if (! $this->exists($project, $from)) {
            return RenameSnippetResult::Missing;
        }

        if ($this->exists($project, $to)) {
            return RenameSnippetResult::Conflict;
        }

        $fromPath = $this->relativePath($project, $from);
        $toPath = $this->relativePath($project, $to);

        if (! Storage::disk(Disk::Snippets)->move($fromPath, $toPath)) {
            logger()->error("Unable to rename the snippet at {$fromPath} to {$toPath}.");

            return RenameSnippetResult::Failed;
        }

        return RenameSnippetResult::Renamed;
    }

    private function relativePath(string $project, string $snippet): string
    {
        return "{$project}/{$snippet}.php";
    }

    private function defaultContent(): string
    {
        return File::get(resource_path('stubs/scratch.php'));
    }

    private function read(string $path): string
    {
        $contents = Storage::disk(Disk::Snippets)->get($path);

        throw_if($contents === null, RuntimeException::class, "The snippet at {$path} is missing.");

        return $contents;
    }
}
