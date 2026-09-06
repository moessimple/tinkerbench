---
paths:
  - 'tests/Browser/**'
---

# Browser

## Browser suite: hard cap of 10 it() blocks, quarantine flaky same-day
tests/Browser is a wiring canary plus a few journey guards, not a second behavior matrix. Vitest + Pest-unit stay the workhorse and keep 100% line/type coverage on app/.

- Hard cap: at most 10 it() blocks across tests/Browser. Enforced by tests/Arch/BrowserSuiteTest.php. A new browser test needs a one-line PR justification that the unit layer cannot catch the regression; otherwise merge or drop an existing one.
- Every browser test ends with ->assertNoJavascriptErrors() or ->assertNoSmoke().
- Flake policy: a browser test that fails nondeterministically moves to ->group('quarantine') and is dropped from the CI browser job the same day; fixed or deleted within a week. A --retry=2 pass in CI is a bug to file, not an accepted state.
- No sleep()/fixed-time waits for synchronization; rely on the auto-waiting assertions. The one allowed timed wait is the autosave debounce settle in AutosaveTest (AUTOSAVE_SETTLE, commented).
- Determinism lives in tests/Browser/Pest.php: isolated snippets disk, FakeHerd, DeadLanguageServerBridgeLauncher, forced Vite manifest. Never point the snippets disk at storage/app/snippets.
