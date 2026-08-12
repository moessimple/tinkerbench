<?php

declare(strict_types=1);

namespace Support;

use Illuminate\Support\Facades\Process;
use InvalidArgumentException;

class Herd
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

        $snippetPath = sys_get_temp_dir().'/tinkerbench-snippet-'.bin2hex(random_bytes(16)).'.php';
        file_put_contents($snippetPath, $this->withOpeningTag($code));

        try {
            $result = Process::forever()->run([
                $this->phpBinary(),
                base_path('src/Support/bin/run-snippet.php'),
                $snippetPath,
            ]);
        } finally {
            unlink($snippetPath);
        }

        if ($result->successful()) {
            return new SnippetRunResult($result->output());
        }

        return new SnippetRunResult($result->output().$result->errorOutput());
    }

    private function bin(): string
    {
        $path = config('services.herd.bin');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.herd.bin configuration must be a non-empty path.');

        return $path;
    }

    // Snippets are normally a bare body with no opening tag, but pasting a complete, already-tagged
    // PHP file is a natural thing to try in a PHP tinkering tool; prepending a tag unconditionally
    // would double it up into a parse error instead of just running the file as given.
    private function withOpeningTag(string $code): string
    {
        return str_starts_with(mb_ltrim($code), '<?php') ? $code : "<?php\n\n{$code}";
    }
}
