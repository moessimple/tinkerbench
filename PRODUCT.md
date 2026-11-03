# Product

<!-- impeccable:product-schema 1 -->

## Platform

web

## Users

A Laravel developer working locally with Laravel Herd, who wants to run a PHP snippet against one of their own projects and see what it did. The tool is opinionated and built for its author's own workflow first. Other Herd users are welcome to clone and use it, but their preferences do not steer decisions.

Typical scene: open in a browser tab beside the IDE for hours, switching between projects and snippets, running small experiments against real databases and services.

## Product Purpose

tinkerbench is a browser-based REPL for any project linked in Herd. Write a snippet, run it inside that project's own runtime, and read everything the run touched in one chronological feed. Success means answering "what does this code actually do in my project?" faster than `php artisan tinker` and with more visibility.

## Positioning

- Free, local, and in the browser: no paid license and no desktop app, it is just another Herd site.
- Zero per-project setup: every Herd-linked project is a target automatically, switchable without leaving the page.
- A run observability feed: dumps, return values, exceptions, output, and for Laravel 12+ targets queries, logs, N+1 warnings, outgoing HTTP, and view traces, all in execution order, plus a timeline of where the run spent its time.
- Editor-grade PHP support: completion, hover docs, and signature help resolved against the target project's own code (Intelephense).

## Operating Context

- Runs at `https://tinkerbench.test` via Herd, on the developer's own machine.
- Core loop: pick project (`⌘P`, `/`), pick or create snippet (`⌘P`, `#`), edit in Monaco with autosave, run (`⌘Enter`), read the feed, click a card to jump to the line that produced it.
- Per-project settings persist: watcher toggles, open output tab.

## Capabilities and Constraints

- Stack: Laravel 13 on PHP 8.5, Inertia v3 with Vue 3, Tailwind CSS v4, Monaco editor. Runner package in `packages/runner`.
- Target projects need PHP 8.2+. Queries, logs, N+1, HTTP, and view cards need a Laravel 12+ target; any other PHP project still gets dumps, return values, exceptions, and output.
- Light and dark theme, following system preference by default.
- Local only, never deployed: no auth, no hosting. It executes arbitrary PHP in real runtimes with real data attached, and the README warning about this must stay prominent.

## Brand Commitments

- Name is lowercase: `tinkerbench`.
- Voice: plain, sentence case, no marketing tone. Acronyms keep their case ("N+1", "SQL").
- Lineage stated openly: a take on Laravel's `tinker`, inspired by Tinkerwell. It does not claim affiliation with either.

## Evidence on Hand

- `images/app.png` (README screenshot) and `images/social-preview.png`.
- README feature list is the factual capability record.
- No users, testimonials, metrics, or adoption numbers exist. Do not fabricate any.

## Product Principles

1. Opinionated over configurable. Build for the author's workflow; add options only when that workflow needs them.
2. Keyboard first. Every action is reachable from the keyboard; `⌘P` and `⌘Enter` are the primary interface, not shortcuts on top of it.
3. Show what the run did, in order. The feed is the product; it stays truthful, chronological, and never hides data without an explicit control.
4. Dense and readable for long sessions. Information density and legibility outrank decoration.
5. Local and honest about risk. Nothing suggests the tool is safe to expose.

## Accessibility & Inclusion

WCAG 2.2 AA in both light and dark themes, including contrast, visible focus, and full keyboard operation. Motion respects `prefers-reduced-motion`.
