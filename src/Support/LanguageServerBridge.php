<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class LanguageServerBridge
{
    public function start(string $projectPath, string $phpVersion): int
    {
        $invoked = Process::options(['create_new_console' => true])->start([
            $this->nvmExec(),
            'node',
            base_path('src/Support/bin/intelephense-bridge.mjs'),
            $projectPath,
            $phpVersion,
        ]);

        $port = null;

        $invoked->waitUntil(function (string $type, string $line) use (&$port): bool {
            if ($type !== 'out') {
                return false;
            }

            $port = (int) mb_trim($line);

            return true;
        });

        throw_if($port === null, InvalidArgumentException::class, 'The language server bridge did not report a port.');

        return $port;
    }

    private function nvmExec(): string
    {
        $path = config('services.tinkerbench.nvm_exec');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.tinkerbench.nvm_exec configuration must be a non-empty path.');

        return $path;
    }
}
