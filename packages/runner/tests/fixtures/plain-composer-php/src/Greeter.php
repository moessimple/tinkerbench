<?php

declare(strict_types=1);

namespace PlainComposerPhp;

class Greeter
{
    public function greet(string $name): string
    {
        return "Hello, {$name}!";
    }
}
