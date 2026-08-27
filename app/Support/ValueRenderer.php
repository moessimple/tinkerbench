<?php

declare(strict_types=1);

namespace App\Support;

use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\HtmlDumper;

class ValueRenderer
{
    public function __construct(
        private VarCloner $cloner = new VarCloner(),
        private HtmlDumper $dumper = new HtmlDumper(),
    ) {}

    public function render(mixed $value, ?string $label = null): string
    {
        $data = $this->cloner->cloneVar($value);

        if ($label !== null) {
            $data = $data->withContext(['label' => $label]);
        }

        return $this->dumper->dump($data, true) ?? '';
    }
}
