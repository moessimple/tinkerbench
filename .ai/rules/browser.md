---
paths:
  - 'tests/Browser/**'
---

# Browser

## tests/Browser is a wiring canary, and every test in it must be robust
tests/Browser proves the real controller -> Inertia -> Vue -> Monaco wiring and a handful of full journeys. Vitest and Pest-unit stay the behavior workhorse and keep 100% line/type coverage on app/; a browser test earns its place only when no unit-level test can catch the regression.

- Run the suite with `composer test:browser`. A bare `vendor/bin/pest tests/Browser` runs nothing: phpunit.xml excludes the `browser` group globally so a plain `pest` or `artisan test` skips it, and only the explicit `--group=browser` in `test:browser` opts back in.
- Every browser test ends with `->assertNoJavaScriptErrors()` or `->assertNoSmoke()`.
- A browser test must be deterministic. Flakiness is a defect in the test, not a CI knob: the CI browser job runs with no `--retry`, so a nondeterministic test fails the build. Fix it or delete it the same day; never add a retry flag to paper over it.
- Synchronise on the auto-waiting assertions, never on `sleep()` or a fixed wait. There is no fixed wait in the suite. `assertScript()` is retried up to the suite timeout like every other assertion (`AwaitableWebpage` wraps them all), so an async effect is waited out by asserting on a browser-side flag, not by sleeping.
- To check browser-side state, install a spy with `script()` before the interaction and read it back with `assertScript()`. Because `assertScript()` retries, a spy that records when an async effect finishes (a `fetch` promise resolving, keyed by what it sent) is how you wait for that effect to reach the server before navigating away: a hard `navigate()` aborts an in-flight request, and `waitForEvent('networkidle')` only checks the document load state, not a later `fetch`.
- Determinism lives in tests/Browser/Pest.php: isolated snippets disk, FakeHerd, DeadLanguageServerBridgeLauncher, forced Vite manifest. Never point the snippets disk at storage/app/snippets.
