---
paths:
  - 'app/Support/SnippetRun/Watchers/**'
---

# Watchers

## Watchers is a sub-area of the SnippetRun feature folder
app/Support/SnippetRun/Watchers/ (DumpWatcher, QueryWatcher, LogWatcher, LazyLoadWatcher) groups the snippet-run signal watchers as one cohesive sub-type of the run pipeline (see support.md for the feature-folder split). Add new snippet-run signal watchers here, not flat in app/Support/SnippetRun/.

Each still gets its 1:1 mirrored unit test at the matching path: app/Support/SnippetRun/Watchers/QueryWatcher.php mirrors tests/Unit/Support/SnippetRun/Watchers/QueryWatcherTest.php (tests/ArchTest.php enforces this on the relative pathname). Every watcher exposes register(Application $app, callable $emit): void.

## Watchers emit FeedItem objects, not arrays
A watcher builds an App\Support\SnippetRun\FeedItems\* object and passes it to $emit. It does not assemble the wire array and does not resolve the snippet line: SnippetRunRecorder stamps FeedItem::$line, folds duplicates/N+1, and calls toArray() when it snapshots. So a watcher constructor takes only what it needs to read its event (usually nothing). Adding a capture kind is a new watcher here, a new FeedItem subclass under app/Support/SnippetRun/FeedItems/, a FeedItemKind case, and a matching variant in the frontend FeedItem union (resources/js/types/index.ts).
