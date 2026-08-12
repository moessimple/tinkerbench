<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Support\Enums\RenameSnippetResult;

class SnippetRepository
{
    /** @return list<string> */
    public function names(string $project): array
    {
        $names = [];

        foreach (Storage::disk('snippets')->files($project) as $file) {
            if (Str::endsWith($file, '.php')) {
                $names[] = Str::of($file)->afterLast('/')->beforeLast('.php')->toString();
            }
        }

        sort($names);

        return $names;
    }

    public function ensureExists(string $project, string $snippet): void
    {
        if ($this->exists($project, $snippet)) {
            return;
        }

        $this->write($project, $snippet, $this->defaultContent());
    }

    public function contents(string $project, string $snippet): string
    {
        return $this->read($this->relativePath($project, $snippet));
    }

    public function write(string $project, string $snippet, string $contents): void
    {
        Storage::disk('snippets')->put($this->relativePath($project, $snippet), $contents);
    }

    public function rename(string $project, string $from, string $to): RenameSnippetResult
    {
        $lock = Cache::lock("tinkerbench:snippet-rename:{$project}:{$to}", 5);
        $lock->block(5);

        try {
            return $this->renameWhileLocked($project, $from, $to);
        } finally {
            $lock->release();
        }
    }

    public function delete(string $project, string $snippet): bool
    {
        if (! $this->exists($project, $snippet)) {
            return false;
        }

        return Storage::disk('snippets')->delete($this->relativePath($project, $snippet));
    }

    public function path(string $project, string $snippet): string
    {
        return Storage::disk('snippets')->path($this->relativePath($project, $snippet));
    }

    private function renameWhileLocked(string $project, string $from, string $to): RenameSnippetResult
    {
        if (! $this->exists($project, $from)) {
            return RenameSnippetResult::Missing;
        }

        if ($this->exists($project, $to)) {
            return RenameSnippetResult::Conflict;
        }

        Storage::disk('snippets')->move(
            $this->relativePath($project, $from),
            $this->relativePath($project, $to),
        );

        return RenameSnippetResult::Renamed;
    }

    private function exists(string $project, string $snippet): bool
    {
        return Storage::disk('snippets')->exists($this->relativePath($project, $snippet));
    }

    private function relativePath(string $project, string $snippet): string
    {
        return "{$project}/{$snippet}.php";
    }

    private function defaultContent(): string
    {
        return "<?php\n\necho 'Hello, world!';";
    }

    private function read(string $path): string
    {
        $contents = Storage::disk('snippets')->get($path);

        throw_if($contents === null, RuntimeException::class, "The snippet at {$path} is missing.");

        return $contents;
    }
}
