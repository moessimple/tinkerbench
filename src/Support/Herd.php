<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class Herd
{
    /** @return array<string, string> */
    public function projects(): array
    {
        return [
            ...$this->projectPaths($this->run([$this->php(), $this->phar(), 'sites', '--json'])),
            ...$this->projectPaths($this->run([$this->php(), $this->phar(), 'parked', '--json'])),
        ];
    }

    public function projectPath(string $project): ?string
    {
        $path = $this->projects()[$project] ?? null;

        if ($path === null) {
            return null;
        }

        return realpath($path) ?: null;
    }

    public function currentProject(): string
    {
        $ownPath = realpath(base_path());

        foreach ($this->projects() as $project => $path) {
            if (realpath($path) === $ownPath) {
                return $project;
            }
        }

        throw new RuntimeException('tinkerbench is not served by Herd under a known site name.');
    }

    public function phpBinary(string $project): string
    {
        $binary = mb_trim($this->run([$this->php(), $this->phar(), 'which-php', $project]));

        return $binary !== '' ? $binary : $this->php();
    }

    public function phpVersion(string $phpBinary): string
    {
        $version = mb_trim($this->run([$phpBinary, '-r', 'echo PHP_VERSION;']));

        return $version !== '' ? $version : 'unknown';
    }

    public function laravelVersion(string $phpBinary, string $projectPath): string
    {
        $probe = 'require $argv[1]."/vendor/autoload.php"; echo (require $argv[1]."/bootstrap/app.php")->version();';
        $version = mb_trim($this->run([$phpBinary, '-r', $probe, $projectPath]));

        return $version !== '' ? $version : 'unknown';
    }

    public function runSnippet(string $code, string $phpBinary, string $projectPath): SnippetRunResult
    {
        // The child process is a snippet the caller wrote, its runtime isn't bounded. This request is
        // itself blocked waiting on it, so without lifting PHP's own max_execution_time the request would
        // fatally time out from under a snippet that is still running fine.
        set_time_limit(0);

        $snippetPath = sys_get_temp_dir().'/tinkerbench-snippet-'.bin2hex(random_bytes(16)).'.php';
        file_put_contents($snippetPath, $this->withOpeningTag($code));

        try {
            $result = Process::forever()->run([
                $phpBinary,
                base_path('src/Support/bin/run-snippet.php'),
                $projectPath,
                $snippetPath,
            ]);
        } finally {
            unlink($snippetPath);
        }

        if ($result->successful()) {
            return new SnippetRunResult($result->output());
        }

        return new SnippetRunResult($result->output().$result->errorOutput());
    }

    private function php(): string
    {
        return $this->bin().'/php';
    }

    private function phar(): string
    {
        return $this->bin().'/herd.phar';
    }

    private function bin(): string
    {
        $path = config('services.herd.bin');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.herd.bin configuration must be a non-empty path.');

        return $path;
    }

    /** @return array<string, string> */
    private function projectPaths(string $json): array
    {
        $entries = json_decode($json, true);

        if (! is_array($entries)) {
            return [];
        }

        $projects = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $site = $entry['site'] ?? null;
            $path = $entry['path'] ?? null;
            if (! is_string($site)) {
                continue;
            }

            if (! is_string($path)) {
                continue;
            }

            $projects[$site] = $path;
        }

        return $projects;
    }

    /** @param list<string> $command */
    private function run(array $command): string
    {
        $result = Process::run($command);

        return $result->output() !== '' ? $result->output() : $result->errorOutput();
    }

    // Snippets are normally a bare body with no opening tag, but pasting a complete, already-tagged
    // PHP file is a natural thing to try in a PHP tinkering tool; prepending a tag unconditionally
    // would double it up into a parse error instead of just running the file as given.
    private function withOpeningTag(string $code): string
    {
        return str_starts_with(mb_ltrim($code), '<?php') ? $code : "<?php\n\n{$code}";
    }
}
