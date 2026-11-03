---
name: tinkerbench
description: A browser REPL for Herd projects, built like a machinist's bench.
colors:
  graphite-ink: "#1a2228"
  aluminium-canvas: "#e6e9eb"
  bench-surface: "#f7f8f8"
  scribe-line: "#c3cad0"
  steel-muted: "#55626c"
  coolant-blue: "#196ca2"
  marking-yellow: "#f0c030"
  measure: "#b38600"
  fault-red: "#b42318"
  heat-amber: "#9a4d00"
  log-green: "#287634"
  http-violet: "#6d4fa0"
  view-steel: "#7a8892"
  editor-rose: "#8a3b6a"
  gutter-steel: "#69727a"
  graphite-ink-dark: "#dce2e6"
  aluminium-canvas-dark: "#1d2125"
  bench-surface-dark: "#262b30"
  scribe-line-dark: "#3a4249"
  steel-muted-dark: "#8d99a3"
  coolant-blue-dark: "#62b0e8"
  measure-dark: "#f0c030"
  fault-red-dark: "#f07466"
  heat-amber-dark: "#f0a04b"
  log-green-dark: "#86d094"
  http-violet-dark: "#b49ae0"
  view-steel-dark: "#8d99a3"
  editor-rose-dark: "#d99ac0"
  gutter-steel-dark: "#88949d"
typography:
  title:
    fontFamily: "IBM Plex Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "1rem"
    fontWeight: 600
    lineHeight: 1.5
  body:
    fontFamily: "IBM Plex Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  label-card:
    fontFamily: "IBM Plex Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.875rem"
    fontWeight: 500
    lineHeight: 1.43
  label:
    fontFamily: "IBM Plex Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 500
    lineHeight: 1.33
  caption:
    fontFamily: "IBM Plex Sans, ui-sans-serif, system-ui, sans-serif"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1.33
  code:
    fontFamily: "IBM Plex Mono, Menlo, monospace"
    fontSize: "0.875rem"
    fontWeight: 400
    lineHeight: 1.43
  editor:
    fontFamily: "IBM Plex Mono, Menlo, monospace"
    fontSize: "1rem"
    fontWeight: 400
    lineHeight: "28px"
  code-small:
    fontFamily: "IBM Plex Mono, Menlo, monospace"
    fontSize: "0.75rem"
    fontWeight: 400
    lineHeight: 1.33
    fontFeature: "tnum"
rounded:
  hairline: "2px"
  sm: "4px"
  md: "6px"
spacing:
  xs: "4px"
  sm: "8px"
  md: "12px"
  lg: "16px"
  xl: "24px"
components:
  feed-card:
    backgroundColor: "{colors.bench-surface}"
    textColor: "{colors.graphite-ink}"
    padding: "12px 16px"
  icon-button:
    backgroundColor: "transparent"
    textColor: "{colors.steel-muted}"
    rounded: "{rounded.sm}"
    size: "32px"
  icon-button-hover:
    backgroundColor: "{colors.scribe-line}"
    textColor: "{colors.graphite-ink}"
  tab:
    textColor: "{colors.steel-muted}"
    typography: "{typography.body}"
    padding: "6px 6px"
  tab-active:
    textColor: "{colors.graphite-ink}"
  kbd:
    backgroundColor: "{colors.bench-surface}"
    textColor: "{colors.graphite-ink}"
    typography: "{typography.code-small}"
    rounded: "{rounded.sm}"
    padding: "2px 6px"
  input-palette:
    backgroundColor: "transparent"
    textColor: "{colors.graphite-ink}"
    typography: "{typography.code}"
    rounded: "{rounded.sm}"
    padding: "6px 10px 6px 32px"
  tag-warning:
    textColor: "{colors.heat-amber}"
    typography: "{typography.label}"
    rounded: "{rounded.sm}"
    padding: "2px 6px"
---

# Design System: tinkerbench

## Overview

**Creative North Star: "The Machinist's Bench"**

tinkerbench is a workbench, not a dashboard. Surfaces are aluminium and graphite, cool and matte, with nothing on them that is not a tool or a reading. Coolant blue carries code keywords and focus. Marking yellow is the scribe's paint: it appears only as small marks (the logo, the active tab, the dark-theme editor cursor). Every feed card carries a colored left edge like a coded tool handle, so its kind reads before its label does.

The system is precise and restrained. It is flat, ruled by thin lines, and dense enough to hold a full run's worth of queries, logs, and dumps beside an editor for hours. Hover is a faint tint, never a lift. The one moving moment is the timeline bars drawing out along their scale, like a reading being taken.

It rejects the stock GitHub look it replaced: no Primer palette, no GitHub editor themes, no tiny all-caps labels.

**Key Characteristics:**
- Two themes, light (aluminium) and dark (graphite), driven by the same CSS custom properties.
- Flat, line-ruled layout; depth only for overlays.
- IBM Plex Sans for prose and controls, IBM Plex Mono for code, keys, and numbers.
- Kind colors on card edges; danger and warning override kind.
- Yellow is a mark, never a fill.

## Colors

A cool, low-chroma metal palette with a small set of coded signal colors. Every color is a CSS custom property in `resources/css/app.css` with a light value on `:root` and a dark value on `.dark`; the `-dark` tokens in the frontmatter are those dark values.

### Primary
- **Coolant Blue**: focus rings, the palette input's focus border, code keywords in the editor, the Query kind edge and timeline bar, the "current" dot in the command palette, VarDumper notes. Brighter in dark mode to hold contrast.

### Secondary
- **Marking Yellow**: the logo tile (in both themes) and the dark-theme editor cursor. The light theme uses an ink cursor.
- **Measure** (`--measure`): the active tab underline. Measure Ochre in the light theme so it reads on aluminium, Marking Yellow in the dark theme.

### Tertiary (kind and signal colors)
- **Log Green**: Log card edge, editor strings, SQL strings, VarDumper strings and keys.
- **HTTP Violet**: HTTP card edge and timeline bar, editor numbers, VarDumper numbers.
- **View Steel**: View card edge. Deliberately quiet; view traces are context, not signal.
- **Fault Red**: exceptions, severe log levels, error toasts, destructive actions. Overrides the kind edge.
- **Heat Amber**: slow and duplicate queries, N+1 warnings. Overrides the kind edge; used as a 15% tint behind warning tags.

### Neutral
- **Aluminium Canvas**: page background and VarDumper block background.
- **Bench Surface**: the workbench panel, cards, palette, toasts, and the editor background.
- **Scribe Line**: every border and divider; at 30 to 60% opacity it is also the hover tint.
- **Graphite Ink**: primary text and active controls.
- **Steel Muted**: secondary text, labels, inactive tabs and their counts, idle icons.

### Editor-only
- **Editor Rose**: PHP variables in the Monaco themes. It appears nowhere outside the editor.
- **Gutter Steel**: inactive line numbers in the editor; the active line number is Graphite Ink.

### Named Rules
**The Marking Paint Rule.** Yellow is only ever a small mark: the logo, the active tab, the cursor. It is never a fill, a button, or a background.

**The Coded Handle Rule.** A feed card's left edge (3px) names its kind: Query blue, Log green, HTTP violet, View steel. Danger and warning replace the kind color, because a broken or slow entry matters more than what kind it is. Kinds without a color keep a Scribe Line edge.

**The Full-Strength Text Rule.** Text never uses an opacity-reduced token (`text-muted/70`) and code never sits on Aluminium Canvas in a color that only passes on Bench Surface. Every text pair holds 4.5:1 in both themes; check the light theme first, it is the tighter one. Decorative marks hidden from assistive technology (the breadcrumb slash) and disabled controls are the only exceptions.

**The Quiet Brackets Rule.** Bracket pairs in the editor stay ink-colored. Colored nesting levels would compete with the kind colors.

## Typography

**Body Font:** IBM Plex Sans (with ui-sans-serif, system-ui)
**Mono Font:** IBM Plex Mono (with Menlo)

**Character:** An engineering pairing from one family: Plex Sans for anything a person reads as prose or clicks, Plex Mono for anything the machine produced. Loaded through the Vite font plugin from Bunny Fonts, weights 400/500/600 (sans) and 400/500 (mono). Monaco runs without ligatures.

### Hierarchy
- **Title** (600, 16px, 1.5): the `tinkerbench` wordmark only. The app has no larger heading.
- **Body** (400, 14px, 1.43): tab labels, card content, timeline rows, empty-state messages.
- **Card Label** (500, 14px, 1.43): the kind label at the top of each feed card. Sentence case.
- **Label** (500, 12px, 1.33): log levels, warning tags, palette group headings. Sentence case.
- **Caption** (400, 12px, 1.33): card footer metadata, `line N` buttons (with tabular figures), the output toolbar, the shortcut legend, empty-feed messages.
- **Code** (400 to 500, 14px, 1.43): feed output, the project/snippet breadcrumb, palette input and results, run summary values.
- **Editor** (400, 16px, 28px line height): the Monaco editor only. Larger than feed code because it is where the user types for hours.
- **Code Small** (400, 12px): counts on tabs and the keys in the shortcut legend. Tab counts use tabular figures.

### Named Rules
**The Machine Voice Rule.** Monospace is for code, keys, and measured values (run summary, timeline, tab counts). UI prose, labels, card metadata, and error messages are sans.

**The Sentence Case Rule.** No all-caps labels and nothing below 12px. Acronyms keep their case ("N+1", "HTTP", "SQL").

**The Tabular Rule.** Numbers that sit in a column or update in place (tab counts, run summary, timeline, line buttons) use tabular figures so they don't jitter. Card footer durations are plain caption text today; give them tabular figures if they ever line up across cards.

## Layout

The page is one centered workbench (max 1432px wide, 16px side gutters, 24px bottom margin) under a slim header with the wordmark, the `project / snippet` breadcrumb, and the keyboard shortcut legend.

Inside, a 48px tool rail (run, clear, command palette; fullscreen and theme pinned to the bottom) sits left of the editor. Editor and output split 3:2. Below 900px the header hides, the panes stack vertically, and the breadcrumb moves into the output toolbar. Fullscreen removes the header, frame, and margins and pins the workbench to the viewport.

The output pane stacks thin bars, each ruled by a bottom border: the toolbar (version, watchers), the run summary (duration, memory), the tab strip, the optional query sort bar, then the scrolling feed. The toolbar and run summary use 8px by 16px padding, the tab strip 8px horizontal with 6px tab padding, the query sort bar 4px by 16px; cards use 12px by 16px. Spacing follows Tailwind's 4px step.

The timeline uses a fixed four-column grid (label, duration, percentage, bar) so values line up as a table, with a 10% tick scale above the bars.

## Elevation & Depth

Flat by default. The workbench, bars, and cards are separated by 1px Scribe Line rules and the step between Aluminium Canvas and Bench Surface, never by shadows. Depth appears only for things that float above the bench.

### Shadow Vocabulary
- **Overlay** (`box-shadow: var(--tw-shadow-2xl)`, i.e. `0 25px 50px -12px rgb(0 0 0 / 0.25)`): the command palette and the error toasts.
- **Scrim** (`background: rgb(0 0 0 / 0.5)`): behind the command palette.

### Named Rules
**The Flat Bench Rule.** Nothing on the bench casts a shadow. A shadow means "this is floating above your work and will go away."

## Shapes

Small, tight corners. Hairline 2px for the focus ring and VarDumper blocks, 4px for buttons, tags, keys, and inputs, 6px for the workbench frame, the run button, the palette, and toasts. Cards have no radius; they are rows on the bench, ruled top to bottom. Status dots are the only full circles. Keyboard keys get a 2px bottom border, the one tactile detail.

## Components

### Buttons
Icon-first and quiet; text appears only where an icon would be ambiguous.
- **Shape:** 4px radius (6px on the run button), 32px square in the tool rail.
- **Default:** transparent background, Steel Muted icon (Graphite Ink for the run button).
- **Hover / Focus:** Scribe Line tint at 30 to 50% and Graphite Ink icon; keyboard focus gets the global 2px Coolant Blue ring at 2px offset.
- **Disabled:** 50% opacity.
- **Toggle buttons** (query sort): Scribe Line at 60% behind the selected option, `aria-pressed` set.

### Tabs
- **Style:** Body type, Steel Muted, with a tabular count in Code Small beside the label.
- **Active:** Graphite Ink, 500 weight, and a 2px inset underline in Measure.
- **Keyboard:** only the active tab is in the tab order; arrow keys, Home, and End move between tabs. Keys pressed with a modifier are left to the browser and the system.

### Feed Cards
The signature component. Each run entry is one card in execution order.
- **Shape:** no radius, 1px bottom rule, 3px left edge in the kind color (see The Coded Handle Rule).
- **Header:** Card Label in Steel Muted on the left; copy button and `line N` jump button on the right.
- **Body:** Code type in Graphite Ink, scrolls horizontally rather than wrapping SQL or dumps.
- **Footer:** Caption metadata in Steel Muted, separated by middle dots; warning tags (Heat Amber text on a 15% Heat Amber tint, 4px radius).

### Inputs / Fields
- **Style:** transparent background, 1px Scribe Line border, 4px radius, Code type; placeholder in sans Steel Muted.
- **Focus:** the border turns Coolant Blue instead of showing the ring (the only controls that replace it).
- **Error:** Fault Red message in Label size below the field.

### Command Palette
Max 448px wide, Bench Surface, 6px radius, Overlay shadow over a 50% black scrim, positioned 15% from the top. A search input with a leading icon, grouped mono results with a Coolant Blue dot marking the current item, row actions revealed on hover or keyboard focus within the row, and a footer legend for the `#` and `/` prefixes.

### Keyboard Keys
Bench Surface (Aluminium Canvas inside the feed), 1px Scribe Line border with a 2px bottom edge, 4px radius, Graphite Ink mono. Code Small in the shortcut legend; the key in the empty-feed hint inherits the surrounding 14px.

### Timeline
Rows on a four-column grid under a Steel Muted 0/50/100% scale ticked every 10%. Bars fill in the kind color (Query blue for database, HTTP violet for HTTP, Graphite Ink at 60% for code) and scale in from the left over 450ms (`cubic-bezier(0.2, 0.7, 0.2, 1)`); the animation is off under `prefers-reduced-motion`.

## Do's and Don'ts

### Do:
- **Do** add every new color as a CSS custom property with a light and a dark value, and check both themes.
- **Do** give a new feed kind its own edge color only if it is signal; otherwise leave it on Scribe Line.
- **Do** use the global Coolant Blue focus ring; replace it only with an equally visible Coolant Blue border.
- **Do** keep the Monaco theme hexes in step with the CSS tokens; Monaco cannot read custom properties.
- **Do** give numbers that sit in a column or update in place tabular figures (see The Tabular Rule).
- **Do** collapse long content behind an explicit Show all / Show less control instead of clipping it with CSS.

### Don't:
- **Don't** use the stock GitHub/Primer palette or GitHub editor themes.
- **Don't** use yellow for anything but small marks (logo, active tab, cursor).
- **Don't** use all-caps micro labels or any text below 12px.
- **Don't** color bracket pairs or add syntax colors that compete with the kind colors.
- **Don't** add shadows to anything that sits on the bench; shadows are for overlays only.
- **Don't** add motion beyond state feedback; the timeline draw-in is the one moving moment.
