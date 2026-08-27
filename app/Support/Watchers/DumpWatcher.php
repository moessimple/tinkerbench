<?php

declare(strict_types=1);

namespace App\Support\Watchers;

use App\Support\SourceLocator;
use App\Support\ValueRenderer;
use Illuminate\Contracts\Foundation\Application;
use Symfony\Component\VarDumper\VarDumper;

class DumpWatcher
{
    public function __construct(
        private SourceLocator $source,
        private ValueRenderer $renderer,
    ) {}

    /**
     * @param  callable(array{kind: 'dump', html: string, line: int|null}): void  $emit
     */
    public function register(Application $app, callable $emit): void
    {
        // Herd::runSnippet() sets VAR_DUMPER_FORMAT=html, which turns VarDumper::setHandler() into a
        // no-op (its guard against overriding an operator-fixed format). Clearing it lets the
        // capturing handler install, so dump() feeds the card list instead of writing to stdout.
        unset($_SERVER['VAR_DUMPER_FORMAT']);

        VarDumper::setHandler(function (mixed $value, ?string $label = null) use ($emit): void {
            $emit([
                'kind' => 'dump',
                'html' => $this->renderer->render($value, $label),
                'line' => $this->source->snippetLine(),
            ]);
        });
    }
}
