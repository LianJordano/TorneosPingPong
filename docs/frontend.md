# Frontend guide

Plain PHP views + one design system in CSS + one small JS file. No build step.

## Structure

```
views/
  layout.php              App shell (sidebar, topbar, tabbar) for logged users; guest shell otherwise
  layout-public.php       Minimal shell for the public TV board (/publico/*)
  partials/head.php       <head>: meta, PWA, fonts, CSS list, app.js
  partials/icons.php      SVG sprite (icon names below)
  partials/flashes.php    Flash messages
public/assets/css/
  core/tokens.css         Colors, type, spacing, radius, shadows (light + dark)
  core/base.css           Reset, typography, focus, .icon
  core/layout.css         Shell: sidebar, topbar, tabbar, guest shell
  core/components.css     UI kit (see below)
  core/utilities.css      Helpers + print rules (loaded last)
  pages/domain.css        Shared domain widgets: bracket, podium, next-match-card, score-chip, swatch
  pages/torneos.css       Tournament management (list, form, hub, prizes, audit)
  pages/partidos.css      Match flow (matches, scorekeeper, standings, bracket page, podium page)
  pages/jugadores.css     Players + ranking
  pages/admin.css         Dashboard, login, settings, users, catalogs, account, glossary, errors
  pages/publico.css       Public TV board
public/assets/js/app.js   SW, install button, drawer, theme toggle, confirm dialog, flash dismiss
```

Rules:

- Use tokens (`var(--…)`), never raw colors, so dark mode works. Page files may only add rules for their own module; anything reusable goes into `core/components.css`.
- Mobile first: base styles for phones, `@media (min-width: …)` to enhance. Breakpoints: 640, 720, 900, 1024, 1100.
- UI copy is Spanish with correct accents. Code, classes and comments are English.
- No inline `style=""` except CSS custom properties fed by data, e.g. `style="--value: 62%"` on `.progress`.
- No emoji as icons. Use `icon('name')`.

## PHP helpers (src/Support/helpers.php)

| Helper | Use |
|---|---|
| `e($s)` | Escape every dynamic value |
| `icon('trophy', 'icon--lg', ?label)` | SVG icon; decorative unless a label is given |
| `nav_current('/torneos')` | Returns ` aria-current="page"` for the active section |
| `initials($name)` | Avatar initials |
| `asset('assets/js/x.js')` | URL with cache-busting version |
| `csrf_field()`, `old($old, 'key')` | Forms |

Icons: home, trophy, paddle, ranking, users, user, user-plus, settings, help, book, logout, login, key, lock, mail, download, upload, menu, x, plus, minus, edit, trash, eye, search, filter, check, check-circle, x-circle, alert, info, chevron-right, chevron-left, chevron-down, chevron-up, arrow-left, arrow-right, sort, calendar, clock, map-pin, table, list, bracket, scoreboard, medal, crown, podium, gift, dollar, shuffle, play, flag, history, star, printer, file, spreadsheet, refresh, external, tv, link, copy, sun, moon, shield, archive, tag, layers, swords, target, activity, more, dot.

## Components (core/components.css)

Page header

```php
<nav aria-label="Ruta"><ol class="breadcrumbs">
  <li><a href="/torneos">Torneos</a></li>
  <li><span aria-current="page">Copa 2026</span></li>
</ol></nav>
<header class="page-head">
  <div>
    <span class="eyebrow">Torneos</span>
    <h1>Título</h1>
    <p>Una línea que explica qué se hace aquí.</p>
  </div>
  <div class="actions"><a class="btn btn--primary" href="…"><?= icon('plus') ?> Nuevo</a></div>
</header>
```

`.hero` / `.page-head--hero`: dark brand banner for the few "hub" pages (dashboard, tournament hub, player profile, ranking). Inside it use `.hero-grid`, `.hero-side`, `.hero-card`, `.meta-row` + `.meta-pill`. Everything else uses the plain `.page-head`.

| Component | Classes |
|---|---|
| Card | `.card`, `.card--flush` (table inside), `.card--muted`, `.card--interactive` (whole card is a link), `.card__header`, `.card__title`, `.card__footer` |
| Section | `.section`, `.section-head` (h2 + p + actions) |
| Grids | `.cols` / `.grid-2`, `.grid-3`, `.grid-4` (all collapse to 1 column on phones) |
| Stats | `.stats` > `.stat` > `.stat__label` (icon + text), `.stat__num`, `.stat__hint`; `.stats--compact` |
| Buttons | `.btn` + `--primary` (one per view area), `--secondary`, `--ghost`, `--danger`, `--danger-soft`, `--success`, `--sm`, `--lg`, `--block`, `--icon` (needs `aria-label`); `.link`, `.link--danger`, `.link--muted`; group with `.actions` |
| Badges | `.badge` + `--gray/green/blue/amber/purple/cyan/red/dark`, `--dot` (shape cue so status isn't color-only). Always include text. |
| Tables | `.table-wrap` > `.table`; add `.table--stack` so rows become cards under 720px: every `<td>` gets `data-label="Columna"`, the main cell gets `.cell--primary`, the actions cell gets `.actions-cell`. Numbers: `.num`. |
| Forms | `.form-grid` (`--3`), `.field` > `<span>` label (or `<label>`), control, `.field__hint`, `.field__error`; `.field--wide`; `.fieldset` + `<legend>`; `.check` (checkbox + text); `.form-actions` (`--sticky`); `.inline-form`; `.form-panel`; `.segmented-choice` > `label.choice-card` |
| Toolbar | `.toolbar` row above lists; `.search` (icon + input); `.toolbar__grow` |
| Feedback | `.flash`/`.alert` + `--success/error/warning/info` (icon + `.flash__body`); `.empty-state` (> `.empty-state__icon`, `.empty-state__title`, `p`, `.actions`); `.hint` |
| Navigation | `.tabs` > `a.tabs__link` with `aria-current="page"` (sub-sections, e.g. tournament hub); `.pagination` > `.pagination__pages` > `.pagination__page` (`.is-active` / `aria-current`, `.is-disabled`) |
| Data | `.datalist` (dl), `.list` > `.list__item` > `.list__body` > `.list__title` + `.list__meta`, `.progress` > `span` with `style="--value: 40%"`, `.avatar` (`--lg`) |
| Utilities | `.muted .small .xsmall .strong .num .nowrap .truncate .text-right .text-center .sr-only .stack .stack-sm .cluster .spread .mt-* .mb-* .maxw-sm .maxw-md .hide-mobile .show-mobile .no-print .text-success .text-danger .text-warning` |

### Accessibility checklist

- Every input has a visible label, or `aria-label` when the context is obvious (a score cell). Errors: `aria-invalid="true"` + `aria-describedby` → `.field__error` id.
- Required: `required` attribute + `<span class="req" aria-hidden="true">*</span>` in the label.
- Icon-only buttons have `aria-label`. Decorative icons stay `aria-hidden` (default of `icon()`).
- State is never color-only: add text, an icon or `.badge--dot`.
- Touch targets ≥ 40px (`.btn` is 42px, `.btn--sm` 36px — avoid `--sm` for primary mobile actions).
- Tables: `<th scope="col">`; sortable headers expose `aria-sort`.

## Behaviour (app.js)

- Destructive or irreversible actions: put `data-confirm="Texto"` on the `<form>` or on the submit button. Optional: `data-confirm-title`, `data-confirm-ok="Eliminar"`, `data-confirm-tone="info"` (non-destructive). Never use `onsubmit="return confirm()"`.
- Theme toggle: any element with `data-theme-toggle`.
- Install app: `#btn-instalar` or `[data-install-app]` (kept `hidden` until the browser offers install).
- Flash close: `data-dismiss="flash"`.
- Print: `data-print` on a button calls `window.print()` (no inline onclick).

## Preserved hooks (do not rename)

| Where | Hook |
|---|---|
| layout | `#nav-toggle`, `#nav-principal`, `#nav-overlay`, `#btn-instalar`, `.is-open`, `body.nav-abierto` |
| torneos/form | `#modalidad-select`, `#emparejamiento-select` |
| torneos/marcador | `#form-marcador`, `#tipo-resultado`, `#picker-ganador`, `#tabla-sets`, radios `name="ganador"` values A/B |
| personas/index | `#form-lote`, `#check-todos`, `.check-jugador`, `form="form-lote"` on row checkboxes |
| torneos/show | `[data-ts]`, `[data-ts-all]`, `[data-ts-none]`, `[data-ts-submit]`, `[data-pick]`, `.no-results`, Tom Select `.ts-*` |
| PHP | `EstadoTorneo::badge()` returns `badge--*` classes |
| print | `.no-print` |

Form `action`, `method`, field `name`s and `csrf_field()` must never change.
