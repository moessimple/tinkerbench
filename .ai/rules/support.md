---
paths:
  - 'app/Support/**'
---

# Support

## Single developer, single machine: don't harden against shared/multi-tenant risks
tinkerbench runs locally on one developer's own machine via Herd, not as a shared internal server or multi-tenant service. It has no authentication and executes arbitrary PHP with no resource limits beyond the process itself. When reviewing or extending App\Support\Herd/snippet execution, don't propose defenses for risks that assume multiple concurrent users or other local OS accounts: output size caps to protect "other users" from one runaway snippet, temp file permission hardening against "other local accounts", rate limiting against multi-actor abuse, etc. A crash or resource exhaustion here affects only the developer running it and is self-recoverable with a restart. Reintroduce such defenses only if the deployment model changes to a shared/team instance.

## Support holds multi-method service/infrastructure objects, no Services/Repositories folder
Classes in app/Support (Herd, SnippetRepository, LanguageServerBridge, SnippetRunner, SnippetRunRecorder) are multi-method service/infrastructure objects, the opposite of Actions' single-execute() shape (see actions.md). There is no app/Services or app/Repositories folder; that role lives in app/Support instead. Put a new repository-like or service-like class here, not in a new Services/Repositories directory.
