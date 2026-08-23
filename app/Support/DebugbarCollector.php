<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use DebugBar\DataCollector\ExceptionsCollector;
use DebugBar\DataCollector\MemoryCollector;
use DebugBar\DataCollector\MessagesCollector;
use DebugBar\DataCollector\TimeDataCollector;
use DebugBar\DataFormatter\DataFormatter;
use DebugBar\DebugBar;
use Fruitcake\LaravelDebugbar\DataCollector\QueryCollector;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\Events\QueryExecuted;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\ContextProvider\SourceContextProvider;
use Symfony\Component\VarDumper\Dumper\ContextualizedDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;
use Symfony\Component\VarDumper\VarDumper;

class DebugbarCollector
{
    /**
     * @param  Closure(ExceptionsCollector): void  $run  Catches and reports its own exceptions to keep
     *                                                   collect() returning normally either way, so the
     *                                                   caller decides whether/how to re-throw afterwards.
     * @return array<array-key, mixed>
     */
    public function collect(Application $app, Closure $run): array
    {
        $debugbar = new DebugBar();

        $debugbar->addCollector($time = new TimeDataCollector());
        $debugbar->addCollector($queries = new QueryCollector());
        $debugbar->addCollector($exceptions = new ExceptionsCollector());
        $debugbar->addCollector($messages = new MessagesCollector());
        $debugbar->addCollector(new MemoryCollector());
        // Debugbar's collectors share one static, process-wide default formatter that renders
        // HTML by default; without this, a non-string dump() value (e.g. dump(42)) would format
        // into MessagesCollector's message_html field instead of the plain message field below.
        $messages->setDataFormatter(new DataFormatter());

        $app->make(DatabaseManager::class)->connection()->listen(function (QueryExecuted $query) use ($queries): void {
            $queries->addQuery($query);
        });

        $this->captureDumps($messages);

        $time->startMeasure('snippet');
        $run($exceptions);
        $time->stopMeasure('snippet');

        return $debugbar->getData();
    }

    /**
     * Herd::runSnippet() sets VAR_DUMPER_FORMAT=html so dd()/dump() keep rendering HTML to stdout; that
     * same env var makes VarDumper::setHandler() a permanent no-op (its own guard against overriding an
     * operator-fixed format), so the html handler it would have installed is rebuilt here and installed
     * directly, then wrapped to also forward each dumped value to the MessagesCollector.
     */
    private function captureDumps(MessagesCollector $messages): void
    {
        unset($_SERVER['VAR_DUMPER_FORMAT']);

        $cloner = new VarCloner();
        $dumper = new ContextualizedDumper(new HtmlDumper(), [new SourceContextProvider()]);

        VarDumper::setHandler(function (mixed $var, ?string $label = null) use ($cloner, $dumper, $messages): void {
            $data = $cloner->cloneVar($var);

            if ($label !== null) {
                $data = $data->withContext(['label' => $label]);
            }

            $dumper->dump($data);

            $messages->addMessage($var);
        });
    }
}
