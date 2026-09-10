<?php

declare(strict_types=1);

namespace App\Support\SnippetRun;

use Dotenv\Dotenv;
use Illuminate\Process\Exceptions\ProcessTimedOutException;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;

class SnippetRunner
{
    private const int DEFAULT_TIMEOUT_SECONDS = 300;

    /**
     * @param  string|null  $scratchDirectory  Directory for the snippet's transient input/debug files;
     *                                         defaults to the system temp directory. Set it to an
     *                                         isolated path when a caller needs the run's temp files
     *                                         kept away from other processes' tinkerbench-* files.
     */
    public function __construct(private ?string $scratchDirectory = null) {}

    public function run(string $code, string $phpBinary, string $projectPath, int $timeoutSeconds = self::DEFAULT_TIMEOUT_SECONDS): SnippetRunResult
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
            $result = Process::path($projectPath)
                ->timeout($timeoutSeconds)
                ->env($this->isolatedEnvironment($projectPath))
                ->run([
                    $phpBinary,
                    base_path('packages/runner/bin/run-snippet.php'),
                    $projectPath,
                    $snippetPath,
                    $debugPath,
                ]);

            $debug = $this->readDebugData($debugPath);
        } catch (ProcessTimedOutException $processTimedOutException) {
            return new SnippetRunResult($processTimedOutException->result->output().$processTimedOutException->result->errorOutput()."\nSnippet timed out after {$timeoutSeconds} seconds.", null);
        } finally {
            if (file_exists($snippetPath)) {
                unlink($snippetPath);
            }

            if (file_exists($debugPath)) {
                unlink($debugPath);
            }
        }

        if (! $result->successful()) {
            return new SnippetRunResult($result->output().$result->errorOutput(), $debug);
        }

        return new SnippetRunResult($result->output(), $debug);
    }

    /**
     * Blanks out every variable tinkerbench's own .env defines so the target's bootstrap sets
     * those keys from its .env. phpdotenv keeps an already-set variable, so a leaked APP_NAME,
     * DB_CONNECTION or CACHE_STORE would otherwise shadow the target's value. Variables tinkerbench
     * does not define (PATH, HOME, SSH agent, proxy) stay in place for snippets that shell out.
     *
     * @return array<string, string|false>
     */
    private function isolatedEnvironment(string $projectPath): array
    {
        return [
            ...array_fill_keys($this->ownEnvironmentKeys(), false),
            'PWD' => $projectPath,
            'VAR_DUMPER_FORMAT' => 'html',
        ];
    }

    /**
     * Parses the single env file Laravel loaded for tinkerbench: environmentFilePath() is
     * `.env.{APP_ENV}` when that path applies, otherwise `.env`. A cached config loads no file,
     * so the path may be absent; the empty list is then correct because nothing was set from one.
     *
     * @return list<string>
     */
    private function ownEnvironmentKeys(): array
    {
        $path = app()->environmentFilePath();

        if (! is_file($path)) {
            return [];
        }

        return array_keys(Dotenv::parse((string) file_get_contents($path)));
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
}
