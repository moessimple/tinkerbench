---
paths:
  - 'app/Support/**'
---

# Support

## Single developer, single machine: don't harden against shared/multi-tenant risks
tinkerbench runs locally on one developer's own machine via Herd, not as a shared internal server or multi-tenant service. It has no authentication and executes arbitrary PHP with no resource limits beyond the process itself. When reviewing or extending App\Support\Herd/snippet execution, don't propose defenses for risks that assume multiple concurrent users or other local OS accounts: output size caps to protect "other users" from one runaway snippet, temp file permission hardening against "other local accounts", rate limiting against multi-actor abuse, etc. A crash or resource exhaustion here affects only the developer running it and is self-recoverable with a restart. Reintroduce such defenses only if the deployment model changes to a shared/team instance.

## Support holds multi-method service/infrastructure objects, no Services/Repositories folder
Classes in app/Support (Herd, SnippetRepository, App\Support\SnippetRun\SnippetRunner, App\Support\SnippetRun\SnippetRunRecorder, App\Support\LanguageServer\LanguageServerBridge) are multi-method service/infrastructure objects, the opposite of Actions' single-execute() shape (see actions.md). There is no app/Services or app/Repositories folder; that role lives in app/Support instead. Put a new repository-like or service-like class here, not in a new Services/Repositories directory.

## Grouped by feature area, not by class type
app/Support is split by what the classes serve, not by pattern:

- `app/Support/SnippetRun/` (`App\Support\SnippetRun`) is the whole snippet-run pipeline: `SnippetRunner`, `SnippetRunRecorder`, `SnippetRunResult`, `SourceLocator` (line attribution), `ExceptionMapper` (throwable to feed item), `ValueRenderer` (dump rendering), `Duration` (feed-item duration formatting), plus its `Watchers/` and `FeedItems/` sub-areas (see watchers.md, feed-items.md).
- `app/Support/LanguageServer/` (`App\Support\LanguageServer`) is the LSP bridge layer: `LanguageServerBridge`, `LanguageServerBridgeLauncher`, `LaravelLspBridge`.
- `app/Support/bin/` stays flat: helper scripts (`.php`, `.mjs`), not classes, shared across areas.
- `Herd.php` and `SnippetRepository.php` stay flat at the Support root: each is a standalone concern with no sibling group, and a one-class folder is noise.

Add a new class to the feature folder it serves. Start a new feature folder only once two or more classes cohere around one area; a lone class stays flat until then. Each class keeps its 1:1 mirrored unit test at the matching path under `tests/Unit/Support/` (tests/ArchTest.php enforces this on the relative pathname, so `app/Support/SnippetRun/SnippetRunner.php` mirrors `tests/Unit/Support/SnippetRun/SnippetRunnerTest.php`).
