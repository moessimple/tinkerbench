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

Extending the existing composer.json / package.json script names rather than introducing new naming conventions:

```
# PHP
composer lint            # rector; pint --parallel
composer lint:check      # pint --parallel --test; rector --dry-run
composer types:check     # phpstan analyse (level: max, bleedingEdge)
composer test             # config:clear; lint:check; types:check; pest --coverage --exactly=100.0
                           # (exact script wiring for type-coverage to be finalized during step 5)

# JS
npm run lint              # eslint . --fix (unchanged)
npm run lint:check        # eslint . (unchanged)
npm run format:check       # prettier --check resources/ (unchanged)
npm run types:check        # vue-tsc --noEmit (unchanged)
npm run test               # vitest run (new)

# Aggregate CI check
composer ci:check          # npm run lint:check; npm run format:check; npm run types:check; npm run test; composer test
```

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

- **Always:** run `composer lint:check`, `composer types:check`, and the full test suite before any commit that
  touches PHP; run the equivalent npm scripts before any commit that touches JS; keep the stdout/CI behavior of
  existing tests unchanged unless a step specifically targets them; extend existing composer.json/package.json
  script names rather than introducing a parallel naming scheme.
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

- `composer lint:check` (Pint + Rector dry-run) passes with zero violations on the full current codebase.
- `composer types:check` (PHPStan `level: max` + bleedingEdge) passes with zero errors.
- `vendor/bin/pest --coverage --exactly=100.0` and `vendor/bin/pest --type-coverage --min=100` both pass.
- `npm run lint:check`, `npm run format:check`, `npm run types:check` (unchanged) all still pass.
- `npx vitest run` passes with at least one real smoke test executed, not just an empty run.
- `.github/workflows/tests.yml`'s single CI job passes end-to-end via `composer ci:check` (or its equivalent),
  with composer/rector/phpstan caching in place and `coverage: xdebug`.
- `composer.json` name, description, and license reflect tinkerbench, not the generic starter-kit skeleton.

## Open Questions

1. Should Vitest carry a coverage threshold too, or stay coverage-free like both reference projects do for their
   JS unit tests?
2. Exact composer/npm script wiring for the new PHP type-coverage check, final names to be settled during step 5
   implementation, following the existing `lint` / `lint:check` / `types:check` / `test` naming pattern.
3. At what concrete trigger do the deferred items (browser tests, the arch-test, supply-chain hygiene) get picked
   back up?
