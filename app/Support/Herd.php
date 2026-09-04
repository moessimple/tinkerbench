<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\SnippetRun\SnippetRunResult;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class Herd
{
    private const int DEFAULT_SNIPPET_TIMEOUT_SECONDS = 300;

    /**
     * @param  string|null  $scratchDirectory  Directory for the snippet's transient input/debug files;
     *                                         defaults to the system temp directory. Set it to an
     *                                         isolated path when a caller needs the run's temp files
     *                                         kept away from other processes' tinkerbench-* files.
     */
    public function __construct(private ?string $scratchDirectory = null) {}

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

    public function projectPathOrFail(string $project): string
    {
        $path = $this->projectPath($project);

        throw_if($path === null, RuntimeException::class, "Unknown Herd project: {$project}");

        return $path;
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

    public function resolveProject(?string $project): string
    {
        return $project ?? $this->currentProject();
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
        return Cache::rememberForever("herd:php-version:{$phpBinary}", fn (): string => $this->resolvePhpVersion($phpBinary));
    }

    public function refreshPhpVersion(string $phpBinary): string
    {
        $version = $this->resolvePhpVersion($phpBinary);

        Cache::forever("herd:php-version:{$phpBinary}", $version);

        return $version;
    }

    public function laravelVersion(string $phpBinary, string $projectPath): string
    {
        return Cache::rememberForever(
            "herd:laravel-version:{$phpBinary}:{$projectPath}",
            fn (): string => $this->resolveLaravelVersion($phpBinary, $projectPath),
        );
    }

    public function refreshLaravelVersion(string $phpBinary, string $projectPath): string
    {
        $version = $this->resolveLaravelVersion($phpBinary, $projectPath);

        Cache::forever("herd:laravel-version:{$phpBinary}:{$projectPath}", $version);

        return $version;
    }

    public function runSnippet(string $code, string $phpBinary, string $projectPath, int $timeoutSeconds = self::DEFAULT_SNIPPET_TIMEOUT_SECONDS): SnippetRunResult
    {
        // The child process is a snippet the caller wrote, bounded to $timeoutSeconds below so a runaway
        // infinite loop can't tie up this request (and the php-fpm worker handling it) forever. This request
        // is itself blocked waiting on it, so PHP's own max_execution_time is lifted past that same bound,
        // with headroom for the process to actually be killed, otherwise the request would fatally time out
        // from under a snippet that Process::timeout() is still waiting to terminate.
        set_time_limit($timeoutSeconds + 30);

        $scratchDirectory = $this->scratchDirectory ?? sys_get_temp_dir();
        $snippetPath = $scratchDirectory.'/tinkerbench-snippet-'.Str::random(32).'.php';
        $debugPath = $scratchDirectory.'/tinkerbench-debug-'.Str::random(32).'.json';
        file_put_contents($snippetPath, $code);

        try {
            // Without this, Symfony VarDumper defaults to its plain-text CliDumper under the CLI SAPI
            // this subprocess runs under, so dd()/dump()/var_dump() output couldn't be told apart from
            // plain text and rendered as an interactive dump.
            $result = Process::timeout($timeoutSeconds)->env(['VAR_DUMPER_FORMAT' => 'html'])->run([
                $phpBinary,
                base_path('app/Support/bin/run-snippet.php'),
                $projectPath,
                $snippetPath,
                $debugPath,
            ]);

            $debug = $this->readDebugData($debugPath);
        } catch (ProcessTimedOutException $processTimedOutException) {
            return new SnippetRunResult($processTimedOutException->result->output().$processTimedOutException->result->errorOutput()."\nSnippet timed out after {$timeoutSeconds} seconds.", null);
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

    private function resolvePhpVersion(string $phpBinary): string
    {
        $version = mb_trim($this->run([$phpBinary, '-r', 'echo PHP_VERSION;']));

        return $version !== '' ? $version : 'unknown';
    }

    private function resolveLaravelVersion(string $phpBinary, string $projectPath): string
    {
        $probe = 'require $argv[1]."/vendor/autoload.php"; echo (require $argv[1]."/bootstrap/app.php")->version();';
        $version = mb_trim($this->run([$phpBinary, '-r', $probe, $projectPath]));

        return $version !== '' ? $version : 'unknown';
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
        return Process::run($command)->throw()->output();
    }
}
