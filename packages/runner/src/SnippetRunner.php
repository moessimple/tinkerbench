<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

use ErrorException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Throwable;
use Tinkerbench\Runner\Watchers\DumpWatcher;
use Tinkerbench\Runner\Watchers\LazyLoadWatcher;
use Tinkerbench\Runner\Watchers\LogWatcher;
use Tinkerbench\Runner\Watchers\QueryWatcher;
use Tinkerbench\Runner\Watchers\ViewWatcher;

class SnippetRunner
{
    /** Native class-constant types need PHP 8.3+; this package's floor is 8.2. */
    private const FATAL_ERROR_MASK = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private bool $persisted = false;

    /**
     * @param  list<string>  $enabledOptionalWatchers  Ids of the "default off" watchers to register for this
     *                                                 run, in addition to the always-on ones (see watchers.md).
     */
    public function run(string $projectPath, string $snippetPath, string $debugPath, array $enabledOptionalWatchers = []): void
    {
        // Invoked as a subprocess under the target project's own Herd-pinned PHP binary, not
        // necessarily tinkerbench's own, so it boots the target project separately from this file's
        // own, already-loaded autoloader. A plain-PHP target with no Composer has none: the basic
        // pipeline then runs with only the runner's own bundled libraries.
        if (is_file($projectPath.'/vendor/autoload.php')) {
            require $projectPath.'/vendor/autoload.php';
        }

        $app = $this->bootTargetApplication($projectPath);

        $source = new SourceLocator($snippetPath);
        $valueRenderer = new ValueRenderer();

        $recorder = new SnippetRunRecorder(
            $app instanceof Application ? [
                new DumpWatcher($valueRenderer),
                new QueryWatcher(),
                new LogWatcher($valueRenderer),
                new LazyLoadWatcher(),
                ...in_array('view', $enabledOptionalWatchers, true) ? [new ViewWatcher($valueRenderer)] : [],
            ] : [],
            new ExceptionMapper($projectPath, $source->path()),
            $source,
        );

        // The basic pipeline registers no watchers, so it captures dumps straight into the recorder
        // instead of through a Watcher's $emit callback.
        if (! $app instanceof Application) {
            DumpCapture::install($valueRenderer, $recorder->appendDump(...));
        }

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

        // `require` yields int(1) for a snippet with no `return` statement and null for a bare
        // `return;`, so both count as "no result". A literal `return 1;` is indistinguishable from
        // the no-return case and likewise shows nothing.
        if ($returned !== null && $returned !== 1) {
            $recorder->appendResult(
                $valueRenderer->render($returned),
                $valueRenderer->renderText($returned),
            );
        }

        $this->persist($recorder, $source, $debugPath, null);
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
            $recorder->appendException($fatal, $source->throwableLine($fatal), includeFrames: false);
        }

        // dump() and toRawSql() can carry binary or malformed-UTF-8 bytes; without these flags one
        // such value makes json_encode() return false and the whole feed is lost for the run.
        $json = json_encode($recorder->snapshot(), JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);

        $fallback = (string) json_encode(['items' => [], 'duration_str' => '', 'peak_memory_str' => '']);

        file_put_contents($debugPath, $json !== false ? $json : $fallback);

        $this->persisted = true;
    }

    /**
     * Boots the target's Laravel application when it has one. Returns null (run() then uses the
     * basic dump/result/exception pipeline) for a target missing either half of a bootable Laravel
     * install, or one whose bootstrap/app.php does not return an Application. The vendor check
     * matches Herd::resolveLaravelVersion() and keeps a bootstrap/app.php with no autoloader from
     * fataling on the require below instead of falling back.
     */
    private function bootTargetApplication(string $projectPath): ?Application
    {
        $bootstrapPath = $projectPath.'/bootstrap/app.php';

        if (! is_file($projectPath.'/vendor/autoload.php') || ! is_file($bootstrapPath)) {
            return null;
        }

        $app = require $bootstrapPath;

        if (! $app instanceof Application) {
            return null;
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }
}
