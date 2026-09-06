<?php

declare(strict_types=1);

namespace App\Support;

use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Process;
use InvalidArgumentException;
use RuntimeException;

class Herd
{
    /** @return array<string, string> */
    public function projects(bool $fresh = false): array
    {
        return $this->cached('herd:projects', $fresh, fn (): array => $this->resolveProjects());
    }

    /** @return list<string> */
    public function projectNames(): array
    {
        return array_keys($this->projects());
    }

    public function projectPath(string $project): ?string
    {
        $path = $this->projects()[$project] ?? null;

        if ($path === null) {
            return null;
        }

        return realpath($path) ?: null;
    }

    public function projectPathOrFail(string $project): string
    {
        $path = $this->projectPath($project);

        throw_if($path === null, RuntimeException::class, "Unknown Herd project: {$project}");

        return $path;
    }

    public function currentProject(): string
    {
        $ownPath = realpath(base_path());

        foreach ($this->projects() as $project => $path) {
            if (realpath($path) === $ownPath) {
                return $project;
            }
        }

        throw new RuntimeException('tinkerbench is not served by Herd under a known site name.');
    }

    public function resolveProject(?string $project): string
    {
        return $project ?? $this->currentProject();
    }

    /**
     * The project plus everything the snippet editor page needs from Herd about it, or null when the
     * name is not a known Herd site. Pass $fresh on a full page load to re-resolve every value through
     * Herd instead of the forever-cache, so a site, PHP version or Laravel upgrade changed out of band
     * is picked up.
     */
    public function snapshotProject(?string $project, bool $fresh = false): ?ProjectSnapshot
    {
        // Re-pull the site list before resolving so a just-added or removed site is seen this request.
        if ($fresh) {
            $this->projects(fresh: true);
        }

        $name = $this->resolveProject($project);
        $path = $this->projectPath($name);

        if ($path === null) {
            return null;
        }

        $phpBinary = $this->phpBinary($name, $fresh);

        return new ProjectSnapshot(
            name: $name,
            path: $path,
            phpBinary: $phpBinary,
            phpVersion: $this->phpVersion($phpBinary, $fresh),
            laravelVersion: $this->laravelVersion($phpBinary, $path, $fresh),
        );
    }

    public function phpBinary(string $project, bool $fresh = false): string
    {
        return $this->cached("herd:php-binary:{$project}", $fresh, fn (): string => $this->resolvePhpBinary($project));
    }

    public function phpVersion(string $phpBinary, bool $fresh = false): string
    {
        return $this->cached("herd:php-version:{$phpBinary}", $fresh, fn (): string => $this->resolvePhpVersion($phpBinary));
    }

    public function laravelVersion(string $phpBinary, string $projectPath, bool $fresh = false): string
    {
        return $this->cached(
            "herd:laravel-version:{$phpBinary}:{$projectPath}",
            $fresh,
            fn (): string => $this->resolveLaravelVersion($phpBinary, $projectPath),
        );
    }

    /**
     * @template TValue
     *
     * @param  Closure(): TValue  $resolve
     * @return TValue
     */
    private function cached(string $key, bool $fresh, Closure $resolve): mixed
    {
        if ($fresh) {
            $value = $resolve();

            Cache::forever($key, $value);

            return $value;
        }

        return Cache::rememberForever($key, $resolve);
    }

    /** @return array<string, string> */
    private function resolveProjects(): array
    {
        return [
            ...$this->projectPaths($this->run([$this->php(), $this->phar(), 'sites', '--json'])),
            ...$this->projectPaths($this->run([$this->php(), $this->phar(), 'parked', '--json'])),
        ];
    }

    private function resolvePhpBinary(string $project): string
    {
        $binary = mb_trim($this->run([$this->php(), $this->phar(), 'which-php', $project]));

        return $binary !== '' ? $binary : $this->php();
    }

    private function resolvePhpVersion(string $phpBinary): string
    {
        $version = mb_trim($this->run([$phpBinary, '-r', 'echo PHP_VERSION;']));

        return $version !== '' ? $version : 'unknown';
    }

    private function resolveLaravelVersion(string $phpBinary, string $projectPath): string
    {
        // A plain-PHP or non-Laravel Composer project has no autoloader or no bootstrap/app.php;
        // running the probe there just writes a PHP fatal to stderr on every full-page load.
        if (! is_file($projectPath.'/vendor/autoload.php') || ! is_file($projectPath.'/bootstrap/app.php')) {
            return 'unknown';
        }

        $probe = 'require $argv[1]."/vendor/autoload.php"; echo (require $argv[1]."/bootstrap/app.php")->version();';
        $version = mb_trim($this->run([$phpBinary, '-r', $probe, $projectPath]));

        return $version !== '' ? $version : 'unknown';
    }

    private function php(): string
    {
        return $this->bin().'/php';
    }

    private function phar(): string
    {
        return $this->bin().'/herd.phar';
    }

    private function bin(): string
    {
        $path = config('services.herd.bin');

        throw_if(! is_string($path) || $path === '', InvalidArgumentException::class, 'The services.herd.bin configuration must be a non-empty path.');

        return $path;
    }

    /** @return array<string, string> */
    private function projectPaths(string $json): array
    {
        $entries = json_decode($json, true);

        if (! is_array($entries)) {
            return [];
        }

        $projects = [];

        foreach ($entries as $entry) {
            if (! is_array($entry)) {
                continue;
            }

            $site = $entry['site'] ?? null;
            $path = $entry['path'] ?? null;
            if (! is_string($site)) {
                continue;
            }

            if (! is_string($path)) {
                continue;
            }

            $projects[$site] = $path;
        }

        return $projects;
    }

    /** @param list<string> $command */
    private function run(array $command): string
    {
        return Process::run($command)->throw()->output();
    }
}
