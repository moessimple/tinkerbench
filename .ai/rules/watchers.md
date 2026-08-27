---
paths:
  - 'app/Support/Watchers/**'
---

# Watchers

## Watchers subfolder is an approved deviation from flat app/Support
app/Support/Watchers/ (DumpWatcher, QueryWatcher, LogWatcher) is an approved exception to .ai/rules/app.md "No area subfolders, flat per type". The three signal watchers are one cohesive sub-type; grouping them keeps app/Support/ scannable. Add new snippet-run signal watchers here, not flat in app/Support/.

Each still gets its 1:1 mirrored unit test at the matching path: app/Support/Watchers/QueryWatcher.php mirrors tests/Unit/Support/Watchers/QueryWatcherTest.php (tests/ArchTest.php enforces this on the relative pathname). Every watcher exposes register(Application $app, callable $emit): void.
