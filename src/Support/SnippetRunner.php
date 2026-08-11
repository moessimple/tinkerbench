<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Contracts\Console\Kernel;

final class SnippetRunner
{
    public function run(string $snippetPath): void
    {
        // base_path() isn't available yet here: this runs in a subprocess where only Composer's
        // autoloader has been required so far, the Laravel container that base_path() reads from
        // doesn't exist until bootstrap/app.php below has run.
        $app = require dirname(__DIR__, 2).'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        $returned = require $snippetPath;

        if (is_string($returned)) {
            echo $returned;
        }
    }
}
