<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\Process\PhpExecutableFinder;

final class Herd implements HerdContract
{
    public function phpBinary(): string
    {
        return $this->bin().'/php';
    }

    public function runSnippet(string $code): SnippetRunResult
    {
        // The child process is a snippet the caller wrote, its runtime isn't bounded. This request is
        // itself blocked waiting on it, so without lifting PHP's own max_execution_time the request would
        // fatally time out from under a snippet that is still running fine.
        set_time_limit(0);

        $php = $this->resolvePhpBinary();

        $snippetPath = sys_get_temp_dir().'/tinkerbench-snippet-'.bin2hex(random_bytes(16)).'.php';
        file_put_contents($snippetPath, "<?php\n\n{$code}");

        try {
            $result = Process::forever()->run([
                $php,
                base_path('src/Support/bin/run-snippet.php'),
                $snippetPath,
            ]);
        } finally {
            unlink($snippetPath);
        }

        return new SnippetRunResult($result->output() !== '' ? $result->output() : $result->errorOutput());
    }

    // Falls back to the PHP CLI binary running this process when the configured Herd path
    // doesn't exist, so the app also runs where Herd isn't installed (CI, other machines).
    private function resolvePhpBinary(): string
    {
        $configured = $this->phpBinary();

        if (is_executable($configured)) {
            return $configured;
        }

        $found = new PhpExecutableFinder()->find();

        throw_if($found === false, RuntimeException::class, 'Could not locate a PHP executable to run the snippet with.');

        return $found;
    }

    private function bin(): string
    {
        $path = config('services.herd.bin');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.herd.bin configuration must be a non-empty path.');

        return $path;
    }
}
