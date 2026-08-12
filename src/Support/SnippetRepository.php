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
    public function names(): array
    {
        $names = [];

        foreach (Storage::disk('snippets')->files() as $file) {
            if (Str::endsWith($file, '.php')) {
                $names[] = Str::of($file)->beforeLast('.php')->toString();
            }
        }

        sort($names);

        return $names;
    }

    public function ensureExists(string $snippet): void
    {
        if ($this->exists($snippet)) {
            return;
        }

        $this->write($snippet, $this->defaultContent());
    }

    public function contents(string $snippet): string
    {
        return $this->read($this->relativePath($snippet));
    }

    public function write(string $snippet, string $contents): void
    {
        Storage::disk('snippets')->put($this->relativePath($snippet), $contents);
    }

    public function rename(string $from, string $to): RenameSnippetResult
    {
        $lock = Cache::lock("tinkerbench:snippet-rename:{$to}", 5);
        $lock->block(5);

        try {
            return $this->renameWhileLocked($from, $to);
        } finally {
            $lock->release();
        }
    }

    public function delete(string $snippet): bool
    {
        if (! $this->exists($snippet)) {
            return false;
        }

        return Storage::disk('snippets')->delete($this->relativePath($snippet));
    }

    public function path(string $snippet): string
    {
        return Storage::disk('snippets')->path($this->relativePath($snippet));
    }

    private function renameWhileLocked(string $from, string $to): RenameSnippetResult
    {
        if (! $this->exists($from)) {
            return RenameSnippetResult::Missing;
        }

        if ($this->exists($to)) {
            return RenameSnippetResult::Conflict;
        }

        Storage::disk('snippets')->move(
            $this->relativePath($from),
            $this->relativePath($to),
        );

        return RenameSnippetResult::Renamed;
    }

    private function exists(string $snippet): bool
    {
        return Storage::disk('snippets')->exists($this->relativePath($snippet));
    }

    private function relativePath(string $snippet): string
    {
        return "{$snippet}.php";
    }

    private function defaultContent(): string
    {
        return "echo 'Hello, world!';";
    }

    private function read(string $path): string
    {
        $contents = Storage::disk('snippets')->get($path);

        throw_if($contents === null, RuntimeException::class, "The snippet at {$path} is missing.");

        return $contents;
    }
}
