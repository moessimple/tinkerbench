<?php

declare(strict_types=1);

namespace App\Support\LanguageServer;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class LanguageServerBridgeLauncher
{
    private const int START_TIMEOUT_SECONDS = 60;

    // A spawned child inherits every file descriptor PHP has open, not just 0/1/2
    // (https://www.php.net/manual/en/function.proc-open.php: only descriptors listed in
    // descriptorspec are redirected, everything else passes through as-is). The bridge is
    // detached and keeps running after this request ends, so it holds any such descriptor
    // open for as long as it runs. If this request is itself running inside a shell pipe
    // (e.g. PHPUnit/Pest with a CI step capturing their output), the bridge ends up holding
    // that pipe open too, and the pipe then never sees EOF. PHP has no userland way to mark a
    // descriptor close-on-exec before that happens (https://github.com/php/php-src/issues/20084),
    // so this script closes every descriptor above 2 itself, right before exec'ing the real
    // command, leaving only the bridge's own stdio (which Process sets up separately) open.
    // Runs under bash, not /bin/sh: Debian/Ubuntu's /bin/sh is dash, which only parses a
    // single-digit descriptor number in a redirection (Ubuntu bug #249620, still open) and
    // aborts the whole script on anything higher, exactly the range a busy PHP process (this
    // one under a parallel test run, for example) routinely has open. bash has no such limit
    // and ships on both Herd's macOS and Ubuntu CI by default.
    //
    // /dev/fd/* can also list a descriptor the shell only opened to read that very directory
    // listing and already closed again by the time this loop gets to it. Closing an
    // already-closed descriptor is a redirection error, and since exec is a POSIX special
    // builtin, that error would otherwise kill this whole script instead of just failing the
    // one redirection, so each descriptor is checked with a plain existence test first (no
    // redirection involved, so it can't trigger that).
    private const string CLOSE_INHERITED_FDS_BEFORE_EXEC = <<<'SH'
        for fd in /dev/fd/*; do
            n=${fd##*/}
            case "$n" in
                0 | 1 | 2 | *[!0-9]*) ;;
                *) [ -e "$fd" ] && eval "exec ${n}<&-" 2>/dev/null ;;
            esac
        done
        exec "$@"
        SH;

    /**
     * @param  list<string>  $args
     */
    public function start(string $scriptPath, array $args): int
    {
        // This request blocks waiting on the bridge process below, bounded by Process's own
        // timeout, so PHP's own max_execution_time is lifted past that same bound, with headroom
        // for the process to actually be killed, otherwise the request would fatally time out
        // from under a bridge that Process::timeout() is still waiting to terminate. Mirrors
        // SnippetRunner::run()'s reasoning.
        set_time_limit(self::START_TIMEOUT_SECONDS + 30);

        $invoked = Process::options(['create_new_console' => true])->timeout(self::START_TIMEOUT_SECONDS)->start([
            '/bin/bash',
            '-c',
            self::CLOSE_INHERITED_FDS_BEFORE_EXEC,
            // Becomes $0 of the script above, so it's excluded from "$@" and only the
            // actual command (nvmExec onward) gets exec'd.
            'bash',
            $this->nvmExec(),
            'node',
            $scriptPath,
            ...$args,
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
