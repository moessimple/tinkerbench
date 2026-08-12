<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;

class SnippetRunner
{
    public function run(string $projectPath, string $snippetPath): void
    {
        // Invoked as a subprocess under the target project's own Herd-pinned PHP binary, not necessarily
        // tinkerbench's own, so it boots the target project separately from this file's own, already-loaded
        // autoloader.
        require $projectPath.'/vendor/autoload.php';

        $app = require $projectPath.'/bootstrap/app.php';

        throw_unless($app instanceof Application, RuntimeException::class, 'bootstrap/app.php did not return an Application instance.');

        $app->make(Kernel::class)->bootstrap();

        $returned = require $snippetPath;

        if (is_string($returned)) {
            echo $returned;
        }
    }
}
