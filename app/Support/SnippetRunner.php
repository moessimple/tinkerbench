<?php

declare(strict_types=1);

namespace App\Support;

use DebugBar\DataCollector\ExceptionsCollector;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Application;
use RuntimeException;
use Throwable;

class SnippetRunner
{
    public function run(string $projectPath, string $snippetPath, string $debugPath): void
    {
        // Invoked as a subprocess under the target project's own Herd-pinned PHP binary, not necessarily
        // tinkerbench's own, so it boots the target project separately from this file's own, already-loaded
        // autoloader.
        require $projectPath.'/vendor/autoload.php';

        $app = require $projectPath.'/bootstrap/app.php';

        throw_unless($app instanceof Application, RuntimeException::class, 'bootstrap/app.php did not return an Application instance.');

        $app->make(Kernel::class)->bootstrap();

        $returned = null;
        $thrown = null;

        $debug = new DebugbarCollector()->collect($app, function (ExceptionsCollector $exceptions) use ($snippetPath, &$returned, &$thrown): void {
            try {
                $returned = require $snippetPath;
            } catch (Throwable $throwable) {
                $exceptions->addThrowable($throwable);
                $thrown = $throwable;
            }
        });

        file_put_contents($debugPath, json_encode($debug));

        throw_if($thrown instanceof Throwable, $thrown); // @phpstan-ignore argument.type (throw_if()'s stub requires $exception pre-typed as Throwable, but $thrown is only reached here once the instanceof check already proves it)

        if (is_string($returned)) {
            echo $returned;
        }
    }
}
