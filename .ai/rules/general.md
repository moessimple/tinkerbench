---
paths:
  - '**/*'
---

# General

## Consistency beats personal style
Consistency (naming, structure, testing style, mocking approach, route conventions) is the top-tier quality bar for this project, above cleverness or terseness. When a stylistic choice is ambiguous, check how the nearest comparable case was already solved in this codebase (or a cited reference project) before deciding, don't default to personal preference. Small deviations (a leading slash on a route, `test()` vs `it()`, a test name that leaks an internal collaborator name) are worth fixing, not "functionally identical, good enough".

## Single developer, single machine: don't harden against shared/multi-tenant risks
tinkerbench runs locally on one developer's own machine via Herd, not as a shared internal server or multi-tenant service. It has no authentication and executes arbitrary PHP with no resource limits beyond the process itself. When reviewing or extending Support\Herd/snippet execution, don't propose defenses for risks that assume multiple concurrent users or other local OS accounts: output size caps to protect "other users" from one runaway snippet, temp file permission hardening against "other local accounts", rate limiting against multi-actor abuse, etc. A crash or resource exhaustion here affects only the developer running it and is self-recoverable with a restart. Considered and reverted once (see git history around Support\Herd.php), reintroduce only if the deployment model changes to a shared/team instance.

## Full, isolated test coverage is mandatory, no silently invented exceptions
Every new/changed class in src/Domain|Application|Support and every new/changed Vue component or JS module needs its own test proving its behavior. This is mandatory, applies equally to PHP and JS/Vue, and includes plain enums, thin controllers, and anything else that looks "too small to test": don't invent an ad hoc exception (e.g. skipping a value-only enum's test) without flagging it to the user first and getting confirmation.

Keep coverage isolated and non-duplicated: one behavior per test, mock only collaborators that already have their own test (tests.md), and don't re-prove behavior a lower layer already covers. When two sibling classes share a shape (e.g. two FormRequest-backed controllers), give them symmetric coverage. Caught in practice: RenameSnippetResult shipped without a test, and UpdateSnippetNameController was missing the invalid-input test its sibling UpdateSnippetContentController had.
