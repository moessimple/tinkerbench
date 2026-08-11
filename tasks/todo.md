# Task List: Quality baseline for tinkerbench

See `tasks/plan.md` for the overview, architecture decisions, dependency graph, and risks. `SPEC.md` is the
source-of-truth spec these tasks implement.

## Task 1: Project identity + PHP floor

**Description:** Replace the generic starter-kit identity in `composer.json` with tinkerbench's own, and raise
the PHP requirement from `^8.3` to `^8.5`.

**Acceptance criteria:**
- [ ] `name` is no longer `laravel/blank-vue-starter-kit`
- [ ] `description` is no longer the generic Laravel skeleton text
- [ ] `license` is deliberately chosen, not silently left as `MIT`
- [ ] `require.php` is `^8.5`

**Verification:**
- [ ] `composer validate --strict`
- [ ] `composer install` resolves cleanly

**Dependencies:** None

**Files likely touched:** `composer.json`

**Estimated scope:** XS

---

## Task 2: PHPStan — level max, bleedingEdge, Pest/Mockery extensions

**Description:** Raise `phpstan.neon` to `level: max` with `bleedingEdge`, add `pest-plugin-phpstan` and
`phpstan-mockery` extensions. Fix whatever `level: max` surfaces rather than downgrading or broadly ignoring it.

**Acceptance criteria:**
- [ ] `phpstan.neon` includes `bleedingEdge.neon`, sets `level: max`, sets `tmpDir`, includes both new extensions
- [ ] `pestphp/pest-plugin-phpstan` and `phpstan/phpstan-mockery` added to `require-dev` (ask first before
      `composer require`)
- [ ] `vendor/bin/phpstan analyse` exits 0; any `ignoreErrors` entry is narrow and justified, not a default escape

**Verification:**
- [ ] `composer types:check` passes with zero errors

**Dependencies:** None (ordered after Task 1 for phase grouping only)

**Files likely touched:** `phpstan.neon`, `composer.json`, `composer.lock`, possibly 1-2 files under `app/`

**Estimated scope:** S

---

## Checkpoint: Phase 1
- [ ] `composer validate --strict` passes
- [ ] `composer types:check` passes with zero PHPStan errors
- [ ] `php artisan test` still passes unchanged
- [ ] Review with user before proceeding

---

## Task 3: Pint strict ruleset

**Description:** Replace the bare `{"preset": "laravel"}` with the strict ruleset from tinkerwell/the reference
project, then normalize the existing codebase once.

**Acceptance criteria:**
- [ ] `pint.json` matches the reference ruleset (adapted only where a rule doesn't apply yet)
- [ ] `vendor/bin/pint --parallel --test` passes

**Verification:**
- [ ] `composer lint:check` passes
- [ ] `php artisan test` still passes after the formatting pass

**Dependencies:** None functionally, sequenced after Task 2 for phase grouping only

**Files likely touched:** `pint.json`, any reformatted files under `app/`/`tests/`/`bootstrap/`/`config/`/`database/`/`routes/`

**Estimated scope:** S

---

## Task 4: Rector

**Description:** Add `rector.php` (LaravelSetList sets, prepared sets, `withPhpSets()`), run it once for real,
wire `rector --dry-run` into `lint:check` and `rector` into `lint`, ordered before Pint per the reference
project's own script.

**Acceptance criteria:**
- [ ] `rector.php` exists, targets `app/`, `bootstrap/app.php`, `config/`, `database/`, `routes/`, `tests/`
- [ ] `driftingly/rector-laravel` and `rector/rector` added to `require-dev` (ask first)
- [ ] `vendor/bin/rector --dry-run` is clean after a real pass has been applied
- [ ] `composer lint` = `["rector", "pint --parallel"]`; `composer lint:check` = `["pint --parallel --test", "rector --dry-run"]`

**Verification:**
- [ ] `composer lint:check` passes (Pint + Rector)
- [ ] `composer types:check` still passes
- [ ] `php artisan test` still passes

**Dependencies:** Task 3

**Files likely touched:** `rector.php` (new), `composer.json`, `composer.lock`, any file Rector's preparedSets touch

**Estimated scope:** S-M

---

## Checkpoint: Phase 2
- [ ] `composer lint:check` passes (Pint + Rector both clean)
- [ ] `composer types:check` still passes
- [ ] `php artisan test` still passes
- [ ] Review with user before proceeding

---

## Task 5: PHP coverage ratchet

**Description:** Add `pestphp/pest-plugin-type-coverage`, wire `pest --type-coverage --min=100` and
`pest --coverage --exactly=100.0`, with a Herd-or-Xdebug fallback for local runs (no Xdebug/PCOV enabled locally
by default).

**Acceptance criteria:**
- [ ] `pestphp/pest-plugin-type-coverage` added to `require-dev` (ask first)
- [ ] A `type-coverage` composer script runs `pest --type-coverage --min=100` and passes
- [ ] `composer test`'s PHP portion includes `pest --coverage --exactly=100.0`, using a Herd-or-`XDEBUG_MODE`
      fallback, and passes

**Verification:**
- [ ] `composer type-coverage` passes
- [ ] The coverage-gated run passes locally via Herd, not just in CI

**Dependencies:** Task 2, Task 4

**Files likely touched:** `composer.json`, `composer.lock`

**Estimated scope:** XS-S

---

## Task 6: JS unit tests (Vitest)

**Description:** Add Vitest + `@testing-library/vue` (+ `jsdom` if required), add `vitest.config.ts`, add one
real example test under `resources/js/`. ESLint/Prettier/vue-tsc stay unchanged.

**Acceptance criteria:**
- [ ] `vitest`, `@testing-library/vue` added to devDependencies (ask first)
- [ ] `vitest.config.ts` resolves the existing Vite/Vue/TS setup rather than duplicating it
- [ ] At least one test renders/exercises a real existing page or component, not a trivial assertion
- [ ] `package.json` gets a `test` script running `vitest run`

**Verification:**
- [ ] `npm run test` passes
- [ ] `npm run lint:check`, `npm run format:check`, `npm run types:check` still pass unchanged

**Dependencies:** None (independent of PHP tasks; sequenced here for the "small steps" preference only)

**Files likely touched:** `package.json`, `vitest.config.ts` (new), one new `*.test.ts` file

**Estimated scope:** S

---

## Checkpoint: Phase 3
- [ ] `composer test` (lint, types, type-coverage, coverage) passes end-to-end
- [ ] `npm run test` (Vitest) passes
- [ ] Pre-existing npm scripts still pass
- [ ] Review with user before proceeding

---

## Task 7: Consolidate `.github/workflows/tests.yml`

**Description:** Update the workflow to run every gate from Tasks 1-6 through one entry point, with
`concurrency`/`cancel-in-progress`, `timeout-minutes`, `coverage: xdebug`, and Composer/npm/Rector/PHPStan
caches.

**Acceptance criteria:**
- [ ] `concurrency: { group: ..., cancel-in-progress: true }` present
- [ ] `setup-php` sets `coverage: xdebug`
- [ ] Composer/npm/Rector/PHPStan caches present, keyed on the relevant lockfile hash
- [ ] The single test step runs every gate from Tasks 1-6 and fails the job if any fails

**Verification:**
- [ ] A real GitHub Actions run on a pushed branch/PR is green end-to-end

**Dependencies:** Tasks 1-6

**Files likely touched:** `.github/workflows/tests.yml`

**Estimated scope:** S

---

## Checkpoint: Complete
- [ ] `.github/workflows/tests.yml` passes end-to-end on a real GitHub Actions run
- [ ] All Success Criteria from `SPEC.md` are met
- [ ] Ready for the user's final review
