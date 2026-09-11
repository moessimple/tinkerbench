<?php

declare(strict_types=1);

use Tinkerbench\Runner\SnippetRunner;

// This is not a web endpoint. The guard also prevents accidental invocation outside the CLI process.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);

    return;
}

require __DIR__.'/../vendor/autoload.php';

if (! isset($argv[1], $argv[2], $argv[3])) {
    fwrite(STDERR, "Usage: run-snippet.php <projectPath> <snippetPath> <debugPath> [enabledWatchers]\n");
    exit(1);
}

[, $projectPath, $snippetPath, $debugPath, $enabledWatchersArg] = $argv + [4 => ''];

$enabledWatchers = $enabledWatchersArg !== '' ? explode(',', $enabledWatchersArg) : [];

(new SnippetRunner())->run($projectPath, $snippetPath, $debugPath, $enabledWatchers);
