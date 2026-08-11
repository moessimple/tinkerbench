# Qualitätsbasis für tinkerbench

## Problem Statement
Wie stellen wir sicher, dass tinkerbench eine CI-erzwungene Qualitätsbasis (Pint, PHPStan, Rector, Pest-Coverage, JS-Tests) hat, bevor die eigentliche Feature-Arbeit beginnt, orientiert an [nunomaduro/laravel-starter-kit-inertia-vue](https://github.com/nunomaduro/laravel-starter-kit-inertia-vue) und `~/.dotfiles/support/tinkerwell`, aber selektiv übernommen statt 1:1 kopiert?

## Recommended Direction
Selektive Übernahme der bewährten PHP-Konfiguration (Pint-Regelwerk, PHPStan `level: max` + bleedingEdge + Pest-/Mockery-Extensions, Rector) nahezu unverändert aus den beiden Referenzen. Die JS-Toolchain bleibt bewusst bei ESLint + Prettier + vue-tsc, kein Umstieg auf `vite-plus`/`bun`. Coverage (Typ und Zeilen) wird ab dem ersten Commit hart auf 100% gesetzt, weil die Codebasis noch leer ist und der Ratchet ab hier nie mehr unterschritten werden darf. Browser-Tests, das Clean-Architecture-Gate und Supply-Chain-Hygiene werden bewusst zurückgestellt, bis es echten Gegenwert dafür gibt.

Referenz-Snapshot (Stand dieser Ideation):
- **tinkerbench (aktuell)**: frisches Laravel-Inertia-Vue-Skeleton, `pint.json` nur nacktes `laravel`-Preset, `phpstan.neon` Level 7, kein `rector.php`, CI mit `coverage: none`.
- **tinkerwell (Referenzprojekt)**: bereits weiter Richtung nunomaduro-Standard, plus eigene Ergänzungen (`pest-plugin-phpstan`, `phpstan-mockery`, Vitest, Playwright, `src/Application`/`src/Domain`/`src/Support`), nutzt `vite-plus` mit npm statt bun.
- **nunomaduro/laravel-starter-kit-inertia-vue (Original)**: `pint.json` striktes Regelwerk, `phpstan.neon` `level: max` + bleedingEdge (ohne die Pest-/Mockery-Extensions), `rector.php` mit LaravelSetList + preparedSets, `vite-plus` + `bun`, Multi-Agent-Tool-Verzeichnisse (`.ai`, `.agents`, `.cursor`, `.gemini` etc.), kein Architektur-Gate.

## Key Assumptions to Validate
- [ ] `level: max` + `bleedingEdge` erzeugt auf dem aktuellen (noch fast leeren) Code keine blockierenden False Positives — zeigt sich bei Schritt 2.
- [ ] Der 100%-Coverage-Ratchet bleibt tragbar, sobald echte Feature-Arbeit beginnt — es sind keine Ausnahmen eingeplant.
- [ ] Rector-Regelset (`LaravelSetList` + preparedSets) verändert den bestehenden Code nicht unerwartet stark — per `rector --dry-run` vor dem ersten scharfen Lauf prüfen.

## MVP Scope
Kleinschritt-Sequenz, jeder Schritt einzeln review-bar:

1. **Projekt-Identität** — `composer.json` (Name, Beschreibung, Lizenz) von `laravel/blank-vue-starter-kit` auf tinkerbench.
2. **PHPStan verschärfen** — `level: max`, `bleedingEdge`, `pest-plugin-phpstan` + `phpstan-mockery` Extensions, `tmpDir`.
3. **Pint-Regelwerk** — striktes Ruleset aus tinkerwell/Original übernehmen, einmal `pint --parallel` über den Bestand laufen lassen.
4. **Rector einführen** — `rector.php` nach Vorlage, `driftingly/rector-laravel` + `rector/rector`, `rector --dry-run` als CI-Check.
5. **PHP-Coverage-Ratchet** — `pest-plugin-type-coverage`, Scripts `test:type-coverage` (`--min=100`) und `test:unit` (`--coverage --exactly=100.0`).
6. **JS-Unit-Tests** — Vitest + Testing-Library, CI-Wiring, ein Beispiel-Smoke-Test. ESLint/Prettier/vue-tsc bleiben unverändert.
7. **CI konsolidieren** — ein Testschritt (`composer test`), Concurrency-Cancel, Timeout, gezieltes Caching (composer/npm/rector/phpstan), `coverage: xdebug` statt `coverage: none`.

## Not Doing (and Why)
- **Browser-Tests (Pest Browser Plugin, Playwright)** — kein Gegenwert ohne echte UI-Flows, würde nur Infrastruktur ohne Zweck aufbauen.
- **Pest `arch()`-Test für Clean Architecture** — erst wenn Domain/Application/Support tatsächlich befüllt werden, vorher totes Gerüst.
- **Multi-Agent-Tool-Verzeichnisse** (`.ai`, `.agents`, `.cursor`, `.gemini` etc.) — bewusst nicht, tinkerbench nutzt nur Claude Code (bereits bei `.factory`/`AGENTS.md` entschieden).
- **vite-plus / bun** — verworfen, ESLint/Prettier/vue-tsc bleiben die JS-Toolchain.
- **`pnpm-workspace.yaml` anfassen** — nicht Teil dieser Arbeit, bleibt für eine spätere, bewusste Entscheidung liegen.
- **Supply-Chain-Hygiene** (`roave/security-advisories`, `update:requirements`-Script) — vorerst zurückgestellt.

## Open Questions
- Bei welchem konkreten Auslöser sollen die zurückgestellten Punkte (Browser-Tests, Architektur-Gate, Supply-Chain-Hygiene) nachgezogen werden?
