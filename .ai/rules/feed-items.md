---
paths:
  - 'app/Support/SnippetRun/FeedItems/**'
---

# Feed Items

## FeedItems hold the output-feed wire shapes, one class per kind
app/Support/SnippetRun/FeedItems/ is a sub-area of the SnippetRun feature folder (like Watchers/; see support.md). FeedItem is an abstract base: public ?int $line (null until stamped) plus abstract toArray(): array<string,mixed>. One concrete class per feed kind (DumpFeedItem, QueryFeedItem, LogFeedItem, NPlusOneFeedItem, ExceptionFeedItem, ResultFeedItem); each toArray() sets 'kind' from a FeedItemKind enum case and owns its own formatting (Duration::format, context JSON encoding, slow threshold). Watchers emit their items into SnippetRunRecorder::append(), which stamps $line from SourceLocator and folds duplicate/count before snapshot() calls toArray(). Two paths skip append() and add the item directly: appendException() (ExceptionMapper already set $line from the caught throwable's own frame) and appendResult() (ResultFeedItem has no line). So a watcher item's line comes from the recorder; an exception item's line comes from the mapper. Every class gets its 1:1 mirrored test under tests/Unit/Support/SnippetRun/FeedItems/ (ArchTest enforces). The toArray() output must stay in sync with the frontend FeedItem union in resources/js/types/index.ts; key order there is irrelevant (JSON), but PHP toBe() assertions pin it.
