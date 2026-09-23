---
paths:
  - 'packages/runner/**'
---

# Runner

## The runner runs from its runtime build, behind the target's autoloader
bin/run-snippet.php runs inside the target project's PHP process, so the target is the host. It loads the target's vendor/autoload.php first, then packages/runner/runtime/autoload.php, and moves the runner's loader behind the target's. Where both ship a package (symfony/var-dumper, polyfills), the target's copy runs, global functions included, since Composer includes each package's function file only once per process.

runtime/ is `composer build` (composer install --no-dev into runtime/, same composer.lock), built by `composer setup` and CI. vendor/ with the dev packages (Pest, Testbench, Laravel 12, Mockery) is for the runner's own tests and tooling only; never load it from bin/. Tests spawning bin/ need runtime/ built. SnippetRunnerTest proves both halves: a snippet gets the target's copy of symfony/var-dumper, its dump() function and its VarDumper class, and no file from vendor/ is loaded. Only the class proves the loader move: Composer includes function files when autoload.php is required, so dump() comes from the target by load order alone.
