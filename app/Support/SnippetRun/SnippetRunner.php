<?php

declare(strict_types=1);

namespace App\Support\SnippetRun;

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
            $result = Process::timeout($timeoutSeconds)->env(['VAR_DUMPER_FORMAT' => 'html'])->run([
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
