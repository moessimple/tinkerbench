<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class LaravelLspBridge
{
    private const int START_TIMEOUT_SECONDS = 60;

    public function start(string $projectPath, string $phpBinary, string $targetPhpBinary): int
    {
        // This request blocks waiting on the bridge process below, bounded by Process's own
        // timeout, so PHP's own max_execution_time is lifted past that same bound, with headroom
        // for the process to actually be killed, otherwise the request would fatally time out
        // from under a bridge that Process::timeout() is still waiting to terminate. Mirrors
        // Herd::runSnippet()'s reasoning.
        set_time_limit(self::START_TIMEOUT_SECONDS + 30);

        $invoked = Process::options(['create_new_console' => true])->timeout(self::START_TIMEOUT_SECONDS)->start([
            $this->nvmExec(),
            'node',
            base_path('app/Support/bin/laravel-lsp-bridge.mjs'),
            $projectPath,
            $phpBinary,
            $targetPhpBinary,
        ]);

        $port = null;

        $invoked->waitUntil(function (string $type, string $line) use (&$port): bool {
            if ($type !== 'out') {
                return false;
            }

            $port = (int) mb_trim($line);

            return true;
        });

        throw_unless(is_int($port) && $port >= 1 && $port <= 65535, InvalidArgumentException::class, 'The language server bridge did not report a valid port.');

        return $port;
    }

    private function nvmExec(): string
    {
        $path = config('services.herd.nvm_exec');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.herd.nvm_exec configuration must be a non-empty path.');

        return $path;
    }
}
