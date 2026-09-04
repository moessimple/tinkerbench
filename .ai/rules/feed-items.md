---
paths:
  - 'app/Support/FeedItems/**'
---

# Feed Items

## FeedItems hold the output-feed wire shapes, one class per kind
app/Support/FeedItems/ is an approved subfolder deviation (like Watchers/). FeedItem is an abstract base: public ?int $line (stamped by SnippetRunRecorder, not the producer) plus abstract toArray(): array<string,mixed>. One concrete class per feed kind (DumpFeedItem, QueryFeedItem, LogFeedItem, NPlusOneFeedItem, ExceptionFeedItem, ResultFeedItem); each toArray() sets 'kind' from a FeedItemKind enum case and owns its own formatting (Duration::format, context JSON encoding, slow threshold). Watchers and ExceptionMapper build these; SnippetRunRecorder mutates line/duplicate/count then calls toArray() at snapshot time. Every class gets its 1:1 mirrored test under tests/Unit/Support/FeedItems/ (ArchTest enforces). The toArray() output must stay in sync with the frontend FeedItem union in resources/js/types/index.ts; key order there is irrelevant (JSON), but PHP toBe() assertions pin it.
