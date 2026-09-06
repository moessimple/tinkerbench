<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

use Symfony\Component\VarDumper\VarDumper;

/**
 * Installs the VarDumper handler that captures every dump()/dd() during a snippet run, rendering
 * each value through a ValueRenderer. Both run pipelines capture dumps this way: the Laravel one
 * through DumpWatcher (which wraps each value into a DumpFeedItem for the Watcher lifecycle), the
 * basic one through SnippetRunner directly, since it has no Application to register a Watcher.
 */
class DumpCapture
{
    /**
     * @param  callable(string $html, string $text): void  $onDump  Called with each dumped value's
     *                                                              rendered HTML and its plain-text form.
     */
    public static function install(ValueRenderer $renderer, callable $onDump): void
    {
        // Herd::runSnippet() sets VAR_DUMPER_FORMAT=html, which turns VarDumper::setHandler() into a
        // no-op (its guard against overriding an operator-fixed format). Clearing it lets the
        // capturing handler install, so dump() feeds the card list instead of writing to stdout.
        unset($_SERVER['VAR_DUMPER_FORMAT']);

        VarDumper::setHandler(function (mixed $value, ?string $label = null) use ($renderer, $onDump): void {
            $onDump(
                $renderer->render($value, $label),
                $renderer->renderText($value, $label),
            );
        });
    }
}
