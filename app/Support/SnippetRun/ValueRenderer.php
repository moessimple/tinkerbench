<?php

declare(strict_types=1);

namespace App\Support\SnippetRun;

use Illuminate\Support\Str;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\CliDumper;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class ValueRenderer
{
    /** Keeps a runaway dump out of the clipboard when the copy button reads this. */
    private const int MAX_TEXT_LENGTH = 20_000;

    public function __construct(
        private VarCloner $cloner = new VarCloner(),
        private HtmlDumper $htmlDumper = new HtmlDumper(),
        private CliDumper $textDumper = new CliDumper(),
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

        return Str::limit($text, self::MAX_TEXT_LENGTH);
    }

    private function cloneValue(mixed $value, ?string $label): Data
    {
        $data = $this->cloner->cloneVar($value);

        return $label === null ? $data : $data->withContext(['label' => $label]);
    }
}
