<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

use ErrorException;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use Symfony\Component\VarDumper\VarDumper;
use Throwable;
use Tinkerbench\Runner\Watchers\DumpWatcher;
use Tinkerbench\Runner\Watchers\LazyLoadWatcher;
use Tinkerbench\Runner\Watchers\LogWatcher;
use Tinkerbench\Runner\Watchers\QueryWatcher;

class SnippetRunner
{
    /** Native class-constant types need PHP 8.3+; this package's floor is 8.2. */
    private const FATAL_ERROR_MASK = E_ERROR | E_PARSE | E_CORE_ERROR | E_COMPILE_ERROR | E_USER_ERROR | E_RECOVERABLE_ERROR;

    private bool $persisted = false;

    public function run(string $projectPath, string $snippetPath, string $debugPath): void
    {
        // Invoked as a subprocess under the target project's own Herd-pinned PHP binary, not
        // necessarily tinkerbench's own, so it boots the target project separately from this file's
        // own, already-loaded autoloader.
        require $projectPath.'/vendor/autoload.php';

        $app = $this->bootTargetApplication($projectPath);

        $source = new SourceLocator($snippetPath);
        $valueRenderer = new ValueRenderer();

        $recorder = new SnippetRunRecorder(
            $app instanceof Application ? [
                new DumpWatcher($valueRenderer),
                new QueryWatcher(),
                new LogWatcher($valueRenderer),
                new LazyLoadWatcher(),
            ] : [],
            new ExceptionMapper($projectPath, $source->path()),
            $source,
        );

        if (! $app instanceof Application) {
            $this->installDumpHandler($recorder, $valueRenderer);
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
     * Boots the target's Laravel application when it has one. Returns null for a Composer project
     * with no bootstrap/app.php, or one whose bootstrap/app.php does not return an Application;
     * run() then uses the basic pipeline (dump/result/exception only) instead of the Laravel one.
     */
    private function bootTargetApplication(string $projectPath): ?Application
    {
        $bootstrapPath = $projectPath.'/bootstrap/app.php';

        if (! is_file($bootstrapPath)) {
            return null;
        }

        $app = require $bootstrapPath;

        if (! $app instanceof Application) {
            return null;
        }

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /**
     * Installs dump capture for the basic pipeline, which has no Application to register a
     * DumpWatcher against. Mirrors DumpWatcher::register()'s handler body: its $app parameter is
     * never read there, so this pipeline reuses the same VarDumper::setHandler() logic directly
     * rather than reshaping the Watcher interface for the one watcher that does not need $app.
     */
    private function installDumpHandler(SnippetRunRecorder $recorder, ValueRenderer $valueRenderer): void
    {
        unset($_SERVER['VAR_DUMPER_FORMAT']);

        VarDumper::setHandler(function (mixed $value, ?string $label = null) use ($recorder, $valueRenderer): void {
            $recorder->appendDump(
                $valueRenderer->render($value, $label),
                $valueRenderer->renderText($value, $label),
            );
        });
    }
}
