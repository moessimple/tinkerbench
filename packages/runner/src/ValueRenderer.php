<?php

declare(strict_types=1);

namespace Tinkerbench\Runner;

use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class ValueRenderer
{
    /**
     * Keeps a runaway dump out of the clipboard when the copy button reads this. Native
     * class-constant types need PHP 8.3+; this package's floor is 8.2.
     */
    private const MAX_TEXT_LENGTH = 20_000;

    public function __construct(
        private readonly VarCloner $cloner = new VarCloner(),
        private readonly HtmlDumper $htmlDumper = new HtmlDumper(),
        private readonly CliDumper $textDumper = new CliDumper(),
    ) {
        $this->textDumper->setColors(false);
    }

    public function render(mixed $value, ?string $label = null): string
    {
        return $this->htmlDumper->dump($this->cloneValue($value, $label), true) ?? '';
    }

    /**
     * Plain-text form of {@see self::render()}, for the feed's copy-to-clipboard button. Truncated at
     * {@see self::MAX_TEXT_LENGTH}.
     */
    public function renderText(mixed $value, ?string $label = null): string
    {
        $text = $this->textDumper->dump($this->cloneValue($value, $label), true) ?? '';

        return $this->limit($text);
    }

    /**
     * Character-bounded truncation with a trailing ellipsis, standing in for
     * Illuminate\Support\Str::limit($text, self::MAX_TEXT_LENGTH) so the runner needs no
     * illuminate/support on the basic (non-Laravel) pipeline. Str::limit also rtrim()s the cut
     * segment; that is dropped here (the cut lands mid var-dump, trailing space there is
     * irrelevant to the clipboard) to avoid mb_rtrim(), which is PHP 8.4+ and this package is 8.2.
     */
    private function limit(string $text): string
    {
        if (mb_strwidth($text, 'UTF-8') <= self::MAX_TEXT_LENGTH) {
            return $text;
        }

        return mb_strimwidth($text, 0, self::MAX_TEXT_LENGTH, '', 'UTF-8').'...';
    }

    private function cloneValue(mixed $value, ?string $label): Data
    {
        $data = $this->cloner->cloneVar($value);

        return $label === null ? $data : $data->withContext(['label' => $label]);
    }
}
