# Implementation Plan: Quality baseline for tinkerbench

## Overview

tinkerbench is being spun off as a standalone project from `~/.dotfiles/support/tinkerwell`, modeled selectively on
[nunomaduro/laravel-starter-kit-inertia-vue](https://github.com/nunomaduro/laravel-starter-kit-inertia-vue). Before
any product feature work starts, this plan wires an enforced quality baseline (static analysis, automated
refactoring safety, code style, coverage ratchet) into CI so future violations block merges instead of being
caught later. It implements exactly the 7 steps converged on in `docs/ideas/quality-baseline.md` and specified in
`SPEC.md`, both already reviewed by the user.

## Architecture Decisions

- **Selective adoption, not a 1:1 port.** PHP tooling (Pint, PHPStan, Rector) is taken close to verbatim from
  `~/.dotfiles/support/tinkerwell` and the upstream starter kit. JS tooling stays on the existing ESLint +
  Prettier + vue-tsc stack; `vite-plus`/`bun` was explicitly rejected.
- **PHP floor raised to `^8.5`** (from `^8.3`) to match the actually-running version, CI, and both reference
  projects, decided during planning because Rector's `withComposerBased(laravel: true)` reads this constraint to
  decide which PHP-version modernizations it's allowed to apply.
- **100% coverage ratchet from the first commit**, both type coverage (`pest-plugin-type-coverage`) and line
  coverage (`--exactly=100.0`), no grace period once turned on in Task 5. Feasible now because the codebase is
  still essentially the framework skeleton.
- **No coverage driver locally by default** (`php -m` shows no Xdebug/PCOV); Laravel Herd is installed, so the
  coverage script needs the same Herd-or-Xdebug fallback tinkerwell already uses.
- **Script naming stays within the existing convention** (`lint`, `lint:check`, `types:check`, `test`,
  `ci:check`) rather than adopting the reference projects' alternate naming (`test:lint`, `test:types`, ...).
- **Explicitly deferred** (see `SPEC.md` Boundaries): browser testing (Playwright/Pest browser plugin), a Clean
  Architecture `arch()` test, `roave/security-advisories` + freshness scripts, and touching
  `pnpm-workspace.yaml`.

## Dependency Graph

```
Task 1: Project identity + PHP floor (composer.json name/description/license/php)
    ▼
Task 2: PHPStan level:max + bleedingEdge + extensions
    ▼
Task 3: Pint strict ruleset
    ▼
Task 4: Rector (depends on Task 3's formatter baseline)
    ▼
Task 5: PHP coverage ratchet (depends on Tasks 2 & 4 being green)
    ▼
Task 7: CI consolidation (depends on Tasks 1-6)

Task 6: JS unit tests (Vitest) — independent of all PHP tasks, touches
    disjoint files; sequenced here only because of the "small, individually
    reviewable steps" preference, not a technical dependency.
```

## Task List

### Phase 1: Identity and static analysis foundation
- [ ] Task 1: Project identity + PHP floor
- [ ] Task 2: PHPStan — level max, bleedingEdge, Pest/Mockery extensions

### Checkpoint: Phase 1
- [ ] `composer validate --strict` passes
- [ ] `composer types:check` passes with zero PHPStan errors
- [ ] Existing test suite (`php artisan test`) still passes unchanged
- [ ] Review with user before proceeding to Phase 2

### Phase 2: Formatting and automated refactoring
- [ ] Task 3: Pint strict ruleset
- [ ] Task 4: Rector

### Checkpoint: Phase 2
- [ ] `composer lint:check` passes (Pint + Rector both clean)
- [ ] `composer types:check` still passes
- [ ] `php artisan test` still passes
- [ ] Review with user before proceeding to Phase 3

### Phase 3: Coverage ratchet and JS tests
- [ ] Task 5: PHP coverage ratchet
- [ ] Task 6: JS unit tests (Vitest)

### Checkpoint: Phase 3
- [ ] `composer test` (lint, types, type-coverage, coverage) passes end-to-end
- [ ] `npm run test` (Vitest) passes
- [ ] Pre-existing npm scripts (`lint:check`, `format:check`, `types:check`) still pass
- [ ] Review with user before proceeding to Phase 4

### Phase 4: CI consolidation
- [ ] Task 7: Consolidate `.github/workflows/tests.yml`

### Checkpoint: Complete
- [ ] `.github/workflows/tests.yml` passes end-to-end on a real GitHub Actions run
- [ ] All Success Criteria from `SPEC.md` are met
- [ ] Ready for the user's final review

Full per-task detail (description, acceptance criteria, verification, dependencies, files, estimated scope) lives
in `tasks/todo.md`.

## Risks and Mitigations

| Risk | Impact | Mitigation |
|---|---|---|
| `phpstan.neon` `level: max` + `bleedingEdge` surfaces more errors than expected on framework-generated code | Medium | Fix in Task 2 before moving on; a narrow, justified `ignoreErrors` entry only for genuine framework limitations, never a blanket downgrade of `level` |
| No Xdebug/PCOV locally; coverage gate (Task 5) can't run without Herd or explicit `XDEBUG_MODE=coverage` | Medium | Mirror tinkerwell's Herd-or-Xdebug fallback; verify the coverage command actually runs locally via Herd |
| PHP floor bump to `^8.5` surfaces unrelated dependency conflicts | Low-Medium | Run `composer update`/`install` right after Task 1 and resolve any conflict before touching Rector |
| Vitest / `@testing-library/vue` peer-compatibility with Vite 8 is unverified | Low-Medium | Task 6 verification requires an actual `npm run test` pass with a real test, not just a successful install |
| `--exactly=100.0` coverage this early means every future feature PR ships tests in the same PR, zero grace period | Accepted by design | Already an explicit, informed choice from the `docs/ideas/quality-baseline.md` session |

## Open Questions

None remaining. The one identified during planning (PHP version floor) was resolved with the user before this
plan was finalized.
