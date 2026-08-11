---
paths:
  - '**/*'
---

# General

## Consistency beats personal style
Consistency (naming, structure, testing style, mocking approach, route conventions) is the top-tier quality bar for this project, above cleverness or terseness. When a stylistic choice is ambiguous, check how the nearest comparable case was already solved in this codebase (or a cited reference project) before deciding, don't default to personal preference. Small deviations (a leading slash on a route, `test()` vs `it()`, a test name that leaks an internal collaborator name) are worth fixing, not "functionally identical, good enough".

## Single developer, single machine: don't harden against shared/multi-tenant risks
tinkerbench runs locally on one developer's own machine via Herd, not as a shared internal server or multi-tenant service. It has no authentication and executes arbitrary PHP with no resource limits beyond the process itself. When reviewing or extending Support\Herd/snippet execution, don't propose defenses for risks that assume multiple concurrent users or other local OS accounts: output size caps to protect "other users" from one runaway snippet, temp file permission hardening against "other local accounts", rate limiting against multi-actor abuse, etc. A crash or resource exhaustion here affects only the developer running it and is self-recoverable with a restart. Considered and reverted once (see git history around Support\Herd.php), reintroduce only if the deployment model changes to a shared/team instance.
