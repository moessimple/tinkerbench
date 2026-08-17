<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class Herd
{
    /** @return array<string, string> */
    public function projects(): array
    {
        return Cache::rememberForever('herd:projects', fn (): array => $this->resolveProjects());
    }

    /** @return array<string, string> */
    public function refreshProjects(): array
    {
        $projects = $this->resolveProjects();

        Cache::forever('herd:projects', $projects);

        return $projects;
    }

    /** @return list<string> */
    public function projectNames(): array
    {
        return array_keys($this->projects());
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
        return Cache::rememberForever("herd:php-binary:{$project}", fn (): string => $this->resolvePhpBinary($project));
    }

    public function refreshPhpBinary(string $project): string
    {
        $binary = $this->resolvePhpBinary($project);

        Cache::forever("herd:php-binary:{$project}", $binary);

        return $binary;
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

        $snippetPath = sys_get_temp_dir().'/tinkerbench-snippet-'.Str::random(32).'.php';
        $debugPath = sys_get_temp_dir().'/tinkerbench-debug-'.Str::random(32).'.json';
        file_put_contents($snippetPath, $code);

        try {
            // Without this, Symfony VarDumper defaults to its plain-text CliDumper under the CLI SAPI
            // this subprocess runs under, so dd()/dump()/var_dump() output couldn't be told apart from
            // plain text and rendered as an interactive dump.
            $result = Process::forever()->env(['VAR_DUMPER_FORMAT' => 'html'])->run([
                $phpBinary,
                base_path('app/Support/bin/run-snippet.php'),
                $projectPath,
                $snippetPath,
                $debugPath,
            ]);

            $debug = $this->readDebugData($debugPath);
        } finally {
            unlink($snippetPath);

            if (file_exists($debugPath)) {
                unlink($debugPath);
            }
        }

        if (! $result->successful()) {
            return new SnippetRunResult($result->output().$result->errorOutput(), $debug);
        }

        return new SnippetRunResult($result->output(), $debug);
    }

    /** @return array<array-key, mixed>|null */
    private function readDebugData(string $debugPath): ?array
    {
        if (! file_exists($debugPath)) {
            return null;
        }

        $decoded = json_decode(file_get_contents($debugPath) ?: '', true);

        return is_array($decoded) ? $decoded : null;
    }

    /** @return array<string, string> */
    private function resolveProjects(): array
    {
        return [
            ...$this->projectPaths($this->run([$this->php(), $this->phar(), 'sites', '--json'])),
            ...$this->projectPaths($this->run([$this->php(), $this->phar(), 'parked', '--json'])),
        ];
    }

    private function resolvePhpBinary(string $project): string
    {
        $binary = mb_trim($this->run([$this->php(), $this->phar(), 'which-php', $project]));

        return $binary !== '' ? $binary : $this->php();
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
}
