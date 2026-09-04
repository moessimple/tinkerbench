---
paths:
  - 'images/**'
---

# Images

## How to regenerate images/app.png and images/social-preview.png

Both PNGs are screenshots of the running Herd app (https://tinkerbench.test) on the
`demo` project's `scratch` snippet (https://tinkerbench.test/demo/scratch).

Drive the browser with the **agent-browser skill** (the project's standing choice for
browser automation). The chrome-devtools MCP also works if it is already running; the
steps below are tool-agnostic and only need "navigate", "run JS in the page", "resize"
and "screenshot to a file".

### `demo/scratch` is a standing exception

You may edit `demo/scratch` at any time to regenerate these screenshots, without asking
first. It is the screenshot fixture, not developer data. Keep it a sensible showcase of
the feed (a query, an N+1, a `dump()`, a `Log::*` with context, a return value). Every
other snippet stays untouchable (see snippets.md).

Order the snippet so the interesting cards land in the viewport: only ~2-3 feed cards
fit above the fold, and the SQL formatter makes every query card tall, so put the
`dump()` (and anything else worth showing) before the N+1 loop / bulk queries. Idiomatic
Laravel only: operate on the Collection you already have (`->sum()`, `->flatMap`), no
`foreach`. Name things for the model: `$users` / `'users'`, not `$authors`. Use
`->all()` on a `pluck()` you `dump()` so the VarDumper shows the values, not a collapsed
`Collection {#…}`.

### Steps

1. Open https://tinkerbench.test/demo/scratch.
2. Theme: match the committed PNGs = **light**. Toggle with the left-rail
   "Switch to light/dark theme" button; switch back to dark when done.
3. Set the snippet. `storage/app/snippets/**` is deny-listed for Edit/Write/Bash and
   Monaco is not on `window`, so go through the page:
   - editor instance:
     `document.querySelector('.h-full.min-w-0.flex-1').__vueParentComponent.setupState.editor`,
     then `editor.setValue(code)`.
   - persist: walk parents to the OpenSnippet instance whose
     `setupState.persistSnippet` exists, then `await persistSnippet(editor.getValue())`.
   - build indentation with `' '.repeat(n)` in the injected script; literal leading
     whitespace in an injected function body can be stripped in transit.
4. Click the "Run snippet" button (not a keyboard chord, see trap below).
5. Screenshot to the file. Viewport sizes at DPR 2:
   - `images/app.png` = 1200x760 -> 2400x1520
   - `images/social-preview.png` = 1280x640 -> 2560x1280
6. Restore: dark theme, normal window size.

### Traps

- Modifier and function-key chords are swallowed inside Monaco
  (`suppressEditorShortcuts`): no Cmd+A / Cmd+V / Cmd+Enter in the editor. Use the
  toolbar buttons.
- Tool output is rendered as GitHub markdown, which **collapses runs of spaces**, so
  `cat -A`, `echo` and `json_encode` all make indentation look like a single space.
  Verify indent numerically instead: `strlen($l) - strlen(ltrim($l, " "))`.
- Keep snippet lines <= ~48 chars. The editor has `wordWrap: 'on'` and longer lines
  wrap in the shot.
