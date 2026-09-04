---
paths:
  - 'app/Support/Watchers/**'
---

# Watchers

## Watchers subfolder is an approved deviation from flat app/Support
app/Support/Watchers/ (DumpWatcher, QueryWatcher, LogWatcher, LazyLoadWatcher) is an approved exception to .ai/rules/app.md "No area subfolders, flat per type". The signal watchers are one cohesive sub-type; grouping them keeps app/Support/ scannable. Add new snippet-run signal watchers here, not flat in app/Support/.

Each still gets its 1:1 mirrored unit test at the matching path: app/Support/Watchers/QueryWatcher.php mirrors tests/Unit/Support/Watchers/QueryWatcherTest.php (tests/ArchTest.php enforces this on the relative pathname). Every watcher exposes register(Application $app, callable $emit): void.

## Watchers emit FeedItem objects, not arrays
A watcher builds an App\Support\FeedItems\* object and passes it to $emit. It does not assemble the wire array and does not resolve the snippet line: SnippetRunRecorder stamps FeedItem::$line, folds duplicates/N+1, and calls toArray() when it snapshots. So a watcher constructor takes only what it needs to read its event (usually nothing). Adding a capture kind is a new watcher here, a new FeedItem subclass under app/Support/FeedItems/, a FeedItemKind case, and a matching variant in the frontend FeedItem union (resources/js/types/index.ts).
