<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use Tinkerbench\Runner\SnippetRunner;

// This is not a web endpoint. The guard also prevents accidental invocation outside the CLI process.
if (PHP_SAPI !== 'cli') {
    http_response_code(404);

    return;
}

if (! isset($argv[1], $argv[2], $argv[3])) {
    fwrite(STDERR, "Usage: run-snippet.php <projectPath> <snippetPath> <debugPath> [enabledWatchers]\n");
    exit(1);
}

[, $projectPath, $snippetPath, $debugPath, $enabledWatchersArg] = $argv + [4 => ''];

$enabledWatchers = $enabledWatchersArg !== '' ? explode(',', $enabledWatchersArg) : [];

$runnerAutoloadPath = __DIR__.'/../runtime/autoload.php';

if (! is_file($runnerAutoloadPath)) {
    fwrite(STDERR, "The runner's runtime is missing. Run `composer build` in packages/runner (composer setup does).\n");
    exit(1);
}

$runStartedAt = hrtime(true);

// The target project is the host, so its autoloader loads first: where both ship a package
// (symfony/var-dumper, the polyfills), the target's copy runs, global functions included, which
// Composer loads only once per package file. A plain-PHP target with no Composer has none.
if (is_file($projectPath.'/vendor/autoload.php')) {
    require $projectPath.'/vendor/autoload.php';
}

// The runtime holds only the runner's own dependencies (`composer build` installs them without
// the dev packages). Composer puts every loader it registers first, so it moves behind the target's.
/** @var ClassLoader $runnerLoader */
$runnerLoader = require $runnerAutoloadPath;
$runnerLoader->unregister();
$runnerLoader->register();

(new SnippetRunner())->run($projectPath, $snippetPath, $debugPath, $runStartedAt, $enabledWatchers);
