<?php

declare(strict_types=1);

namespace App\Support;

use App\Support\Watchers\DumpWatcher;
use App\Support\Watchers\LogWatcher;
use App\Support\Watchers\QueryWatcher;
use ErrorException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;
use Throwable;

class SnippetRunner
{
    private const int FATAL_ERROR_MASK = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private bool $persisted = false;

    public function run(string $projectPath, string $snippetPath, string $debugPath): void
    {
        // Invoked as a subprocess under the target project's own Herd-pinned PHP binary, not
        // necessarily tinkerbench's own, so it boots the target project separately from this file's
        // own, already-loaded autoloader.
        require $projectPath.'/vendor/autoload.php';

        $app = require $projectPath.'/bootstrap/app.php';

        throw_unless($app instanceof Application, RuntimeException::class, 'bootstrap/app.php did not return an Application instance.');

        $app->make(Kernel::class)->bootstrap();

        $source = new SourceLocator($snippetPath);

        $recorder = new SnippetRunRecorder(
            new DumpWatcher($source, new ValueRenderer()),
            new QueryWatcher($source),
            new LogWatcher($source),
            new ExceptionMapper($projectPath),
        );

        // Safety net for the exit paths run() can't return from: dd()/die()/exit() and fatals.
        // On the normal and caught-exception paths run() persists below and this no-ops.
        register_shutdown_function(fn () => $this->persist($recorder, $source, $debugPath, error_get_last()));

        $returned = null;

        try {
            $recorder->record($app, function () use ($snippetPath, &$returned): void {
                $returned = require $snippetPath;
            });
        } catch (Throwable $throwable) {
            $recorder->appendException($throwable, $source->throwableLine($throwable));
        }

        $this->persist($recorder, $source, $debugPath, null);

        if (is_string($returned)) {
            echo $returned;
        }
    }

    /**
     * Writes the run snapshot to $debugPath exactly once. $lastError is error_get_last() when
     * called from the shutdown handler: a fatal-class entry there is one that never surfaced as a
     * Throwable (memory exhaustion, timeout), so it is synthesized into an exception item.
     *
     * @param  array{type: int, message: string, file: string, line: int}|null  $lastError
     */
    public function persist(SnippetRunRecorder $recorder, SourceLocator $source, string $debugPath, ?array $lastError): void
    {
        if ($this->persisted) {
            return;
        }

        if ($lastError !== null && ($lastError['type'] & self::FATAL_ERROR_MASK) !== 0) {
            $fatal = new ErrorException($lastError['message'], 0, $lastError['type'], $lastError['file'], $lastError['line']);
            $recorder->appendException($fatal, $source->throwableLine($fatal));
        }

        file_put_contents($debugPath, json_encode($recorder->snapshot()));

        $this->persisted = true;
    }
}
