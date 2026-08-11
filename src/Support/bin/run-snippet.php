<?php

declare(strict_types=1);

use Support\SnippetRunner;

// This is not a web endpoint. The guard also prevents accidental invocation outside the CLI process.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);

    return;
}

require __DIR__.'/../../../vendor/autoload.php';

[, $snippetPath] = $argv; // @phpstan-ignore variable.undefined (only ever invoked as a CLI subprocess, see comment above)

new SnippetRunner()->run($snippetPath);
