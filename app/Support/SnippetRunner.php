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

        // One shutdown callback is the only writer of the snapshot, so a single mechanism covers
        // every exit path: normal completion, dd()/die()/exit(), and fatals that never surface as a
        // Throwable (recovered here from error_get_last()).
        register_shutdown_function(function () use ($recorder, $source, $debugPath): void {
            $error = error_get_last();

            if ($error !== null && ($error['type'] & self::FATAL_ERROR_MASK) !== 0) {
                $fatal = new ErrorException($error['message'], 0, $error['type'], $error['file'], $error['line']);
                $recorder->appendException($fatal, $source->throwableLine($fatal));
            }

            file_put_contents($debugPath, json_encode($recorder->snapshot()));
        });

        $returned = null;

        try {
            $recorder->record($app, function () use ($snippetPath, &$returned): void {
                $returned = require $snippetPath;
            });
        } catch (Throwable $throwable) {
            $recorder->appendException($throwable, $source->throwableLine($throwable));
        }

        if (is_string($returned)) {
            echo $returned;
        }
    }
}
