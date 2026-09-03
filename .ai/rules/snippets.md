---
paths:
  - 'storage/app/snippets/**'
---

# Snippets are the developer's data, not the agent's

The `.php` files under `storage/app/snippets/**` are the developer's own saved snippets — the ones the command
palette lists. They are user data. Not source, not test fixtures, not yours to tidy. They are local and
per-machine (the directory is gitignored); the path pattern is stable, the contents are precious.

## Never mutate an existing snippet, by any route

No create, rename, delete, or content edit of an existing snippet through any of:

- editing the file under `storage/app/snippets/**` directly;
- calling `PUT` / `PATCH` / `POST` / `DELETE` on `/api/projects/{project}/snippets/...` (curl or otherwise);
- driving the running app via browser automation to run, save, rename, or delete a snippet. Running a snippet in
  the editor autosaves it on a debounce, so "just running it to see the output" is a mutation.

## Prefer not touching a stored snippet at all

`POST /api/projects/{project}/snippets/executions` with an inline `code` body runs arbitrary PHP against the
project and returns the full feed **without persisting anything**. Use it for backend and feed-pipeline checks,
and to reproduce a snippet a developer pasted into the chat. This covers almost every verification need that
isn't specifically about the editor UI.

## Exceptions

1. A task that explicitly names one snippet to change (e.g. a spec that says "update `demo/scratch`"). Only that
   one, confirm first, and treat it as the deliverable, not a side effect.
2. The snippet named `agent-scratch` in any project is the agent sandbox — free to create, edit, run, and
   delete. Nothing else.

## If you mutated one by accident

Restore it byte-for-byte from the content you captured before the change, and tell the developer which snippet
and why. If you have no captured original, stop and say so rather than guessing.
