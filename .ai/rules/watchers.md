---
paths:
  - 'packages/runner/src/Watchers/**'
---

# Watchers

Watchers live in the `tinkerbench/runner` package (`Tinkerbench\Runner\`), the subprocess that runs a snippet in-process under the target project's own PHP. This is not the main `app/` tree; the package has its own `composer.json`, `phpstan.neon`, `pint.json` and test suite under `packages/runner/tests/`.

## Watchers is a sub-area of the runner's snippet-run pipeline
packages/runner/src/Watchers/ (DumpWatcher, QueryWatcher, LogWatcher, LazyLoadWatcher) groups the snippet-run signal watchers as one cohesive sub-type of the run pipeline. Add new snippet-run signal watchers here, not flat in packages/runner/src/.

Each gets its 1:1 mirrored unit test at the matching path: packages/runner/src/Watchers/QueryWatcher.php mirrors packages/runner/tests/Watchers/QueryWatcherTest.php (packages/runner/tests/ArchTest.php enforces this on the relative pathname). Every watcher implements the `Watcher` interface: `register(Application $app, callable $emit): void`.

## Watchers emit FeedItem objects, not arrays
A watcher builds a `Tinkerbench\Runner\FeedItems\*` object and passes it to $emit. It does not assemble the wire array and does not resolve the snippet line: SnippetRunRecorder stamps FeedItem::$line, folds duplicates/N+1, and calls toArray() when it snapshots. So a watcher constructor takes only what it needs to read its event (usually nothing). Adding a capture kind is a new watcher here, a new FeedItem subclass under packages/runner/src/FeedItems/, a FeedItemKind case, and a matching variant in the frontend FeedItem union (resources/js/types/index.ts).
