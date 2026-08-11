# Spec: Quality baseline

## Objective

tinkerbench is being spun off as a standalone project from `~/.dotfiles/support/tinkerwell`, modeled selectively on
[nunomaduro/laravel-starter-kit-inertia-vue](https://github.com/nunomaduro/laravel-starter-kit-inertia-vue). Before
any product feature work starts, the repository needs an enforced quality baseline: static analysis, automated
refactoring safety, code style, and a coverage ratchet, all wired into CI so violations block merges rather than
being caught later.

The "user" of this spec is whoever writes code in this repo next, human or agent. Success looks like: every commit
that touches PHP or JS is checked by the same set of gates the codebase will carry going forward, and those gates
are already green on the current (still empty) codebase before real feature work begins.

This spec covers exactly the seven steps from `docs/ideas/quality-baseline.md`. Deferred items (browser testing, a
Clean Architecture `arch()` test, supply-chain hygiene, the `vite-plus`/`bun` toolchain, touching
`pnpm-workspace.yaml`) are explicitly out of scope, see Boundaries.

## Tech Stack

Current (unchanged): PHP ^8.3 (running 8.5), Laravel ^13.17, Inertia 3, Vue 3, Vite, Tailwind 4, TypeScript,
Pest ^5.1, Larastan ^3.9, Laravel Pint ^1.27, ESLint 9, Prettier 3, vue-tsc, npm.

New dev dependencies (versions to be resolved at `composer require` / `npm install` time):

- `rector/rector`, `driftingly/rector-laravel` — automated refactoring / staying current with Laravel & PHP
- `phpstan/phpstan-mockery` — PHPStan extension for Mockery mocks
- `pestphp/pest-plugin-phpstan` — PHPStan extension for Pest test files
- `pestphp/pest-plugin-type-coverage` — enforces PHP type coverage
- `vitest`, `@testing-library/vue` (+ `jsdom` if required by Vitest's DOM environment) — JS unit testing

No change to the package manager (npm stays) and no `vite-plus`/`bun` adoption.

## Commands

Matching nunomaduro/laravel-starter-kit-inertia-vue's `test:*` naming and composition (each PHP script calls its
JS counterpart as its last step), superseding this spec's earlier decision to keep tinkerbench's pre-existing
`*:check` naming:

```
# PHP
composer lint                # rector; pint --parallel; npm run lint
composer test:lint           # pint --parallel --test; rector --dry-run; npm run test:lint
composer test:types          # phpstan analyse (level: max, bleedingEdge); npm run test:types
composer test:type-coverage  # pest --type-coverage --min=100
composer test:unit           # pest --coverage --exactly=100.0 (Herd or Xdebug); npm run test:unit
composer test                # config:clear; test:type-coverage; test:unit; test:lint; test:types

# JS
npm run lint                 # prettier --write resources/; eslint . --fix
npm run test:lint            # prettier --check resources/; eslint .
npm run test:types           # vue-tsc --noEmit
npm run test:unit            # vitest run
npm run test                 # test:lint; test:types; test:unit
```

`composer ci:check` was removed: `composer test` now transitively runs every JS check too, so keeping both
names for the same thing would have been redundant. CI calls `composer test` directly, matching upstream.

## Project Structure

```
composer.json                  → project identity (name/description/license), new dev deps, extended scripts
package.json                   → new devDependencies (vitest, @testing-library/vue), new test script
phpstan.neon                   → level: max, bleedingEdge, + pest-plugin-phpstan / phpstan-mockery includes, tmpDir
pint.json                      → strict rule set (declare_strict_types, final_class, strict_comparison,
                                   ordered_class_elements, ...), ported from tinkerwell / the reference starter kit
rector.php                     → new file: LaravelSetList + preparedSets, matching the reference starter kit
vitest.config.ts               → new file
resources/js/**/*.test.ts      → first example smoke test(s), scaffolding only
.github/workflows/tests.yml    → single consolidated test step, concurrency cancel-in-progress, timeout,
                                   composer/rector/phpstan caching, coverage: xdebug (was: none)
```

## Code Style

Existing conventions (PSR-4 autoload roots, Laravel/Spatie conventions per `CLAUDE.md` and the
`spatie-laravel-php` skill) are unchanged. The new Pint ruleset raises the bar for new and touched PHP code:

```php
<?php

declare(strict_types=1);

namespace App\Support;

final class ExampleService
{
    public function __construct(
        private readonly SomeDependency $dependency,
    ) {}
}
```

Key additions over the current bare `laravel` preset: `declare_strict_types`, `final_class` /
`final_internal_class`, `strict_comparison`, `ordered_class_elements`, `protected_to_private`,
`global_namespace_import`. Existing code gets normalized once via `pint --parallel` as part of step 3.

## Testing Strategy

- **PHP:** Pest, mirroring `app/` under `tests/`. Two enforced ratchets from the first commit of this work
  onward, no per-file exemptions planned:
  - Type coverage: `pest --type-coverage --min=100` (via `pest-plugin-type-coverage`)
  - Line coverage: `pest --coverage --exactly=100.0` (requires Xdebug or PCOV; CI switches from
    `coverage: none` to `coverage: xdebug`)
- **JS:** Vitest + `@testing-library/vue` for unit tests. No coverage threshold enforced (see Open Questions).
  ESLint, Prettier and `vue-tsc` remain the lint/format/type gates, unchanged.
- **Out of scope for this spec:** Playwright / Pest browser testing, any Clean Architecture `arch()` test.

## Boundaries

- **Always:** run `composer test:lint`, `composer test:types`, and the full test suite before any commit that
  touches PHP; run the equivalent npm scripts before any commit that touches JS; keep the stdout/CI behavior of
  existing tests unchanged unless a step specifically targets them; use nunomaduro/laravel-starter-kit-inertia-vue's
  `test:*` script naming and composition (see Commands) for anything quality-check related, rather than inventing
  a parallel scheme.
- **Ask first:** installing any new Composer or npm dependency, even the ones named in this spec (per this
  project's Laravel Boost / `CLAUDE.md` rule: "Do not change the application's dependencies without approval");
  any change to `.github/workflows/tests.yml` beyond what's listed here; raising `phpstan.neon`'s level past
  `max` or changing `pint.json`'s preset away from `laravel`.
- **Never:** introduce `vite-plus` or `bun`; add browser-testing infrastructure (Playwright,
  `pest-plugin-browser`) in this phase; add a Clean Architecture `arch()` test in this phase; touch
  `pnpm-workspace.yaml`; add multi-agent-tool config directories (`.ai`, `.agents`, `.cursor`, `.gemini`, etc.);
  add `roave/security-advisories` or an `update:requirements` script in this phase; lower the coverage ratchet
  once it is turned on in step 5.

## Success Criteria

- `composer test:lint` (Pint + Rector dry-run + npm test:lint) passes with zero violations on the full codebase.
- `composer test:types` (PHPStan `level: max` + bleedingEdge + npm test:types) passes with zero errors.
- `composer test:type-coverage` and `composer test:unit` (line coverage + Vitest) both pass at 100%.
- `composer test` passes end-to-end and is the single entry point CI calls.
- `.github/workflows/tests.yml`'s single CI job passes end-to-end via `composer test`, with composer/rector/phpstan
  caching in place and `coverage: xdebug`.
- `composer.json` name, description, and license reflect tinkerbench, not the generic starter-kit skeleton.

## Open Questions

1. Should Vitest carry a coverage threshold too, or stay coverage-free like both reference projects do for their
   JS unit tests?
2. At what concrete trigger do the deferred items (browser tests, the arch-test, supply-chain hygiene) get picked
   back up?
