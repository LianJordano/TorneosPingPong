# Frontend UX/UI redesign

Objective: redesign the whole frontend (views + CSS + JS) for excellent UX/UI; restructuring allowed.
Branch: `feature/frontend-ux-redesign` (from `develop`). Delivery: ask-on-risk. Test runner: `vendor/bin/phpunit` (no view tests exist; visual/structural checks via `php -l`, HTTP smoke on `php -S localhost:8001 -t public`).

## Specs

- S1. User request (verbatim): "ajusta todo el frontend para que quede con excelente ux/ui si quieres lo reestructuras".
- S2. Every view in `views/` is covered by the redesign (25 files), consistent design system, no visual regressions in content (all data, actions and forms that exist today remain reachable and functional).
- S3. Behavior preserved: every route, form `action`/`method`/field `name`, CSRF field, and JS hook listed in docs/frontend.md "Preserved hooks" keeps working.
- S4. Accessibility baseline (from audit): visible `:focus-visible`, labelled inputs, no emoji-as-icon (SVG icons, `aria-hidden`), state not by color alone, touch targets >= 40px, `aria-current` in navigation.
- S5. Responsive: usable from 360px phones to desktop; data tables collapse to stacked cards on small screens.
- S6. Restructure: CSS split into design-system core + per-module page files; layout inline JS moved to `public/assets/js/app.js`; public board gets its own minimal layout.
- Assumption: Spanish (with correct accents) for UI copy, since the project UI is Spanish; light + dark theme.

## Tasks

| ID | Specs | Scope | Route | Status | Commit |
|----|-------|-------|-------|--------|--------|
| T1 | S2-S6 | Design system core (tokens/base/layout/components/utilities CSS), icon sprite + `icon()` helper, new `layout.php` shell (sidebar + mobile drawer + bottom nav), `app.js` (SW, install, drawer, confirm dialog, theme), EstadoTorneo badge colors, docs/frontend.md | inline | done (suite 62 OK, login smoke 200) | 23dc5f6 |
| T2 | S2-S5 | Torneos management views: torneos/index, form, show, premios, auditoria + `pages/torneos.css` | delegated (parallel units, disjoint surfaces) | done | 71be288 |
| T3 | S2-S5 | Match flow views: torneos/partidos, marcador, tabla, bracket, podio + `pages/partidos.css` + shared `pages/domain.css` | delegated (parallel) | done | 6b0c684 |
| T4 | S2-S5 | Players & ranking: personas/index, form, show, ranking/index + `pages/jugadores.css` | delegated (parallel) | done | 806e364 |
| T5 | S2-S5 | Admin & misc: dashboard, auth/login, configuracion/index, usuarios, catalogos/index, cuenta/password, glosario, error + `pages/admin.css` | delegated (parallel) | done | e32b303 |
| T6 | S2-S6 | Public board: publico/ver, no-disponible, `layout-public.php`, PublicoController layout + `pages/publico.css` | delegated (parallel) | done | c3ed904 |
| T7 | S2-S6 | Seam: remove legacy app.css, full suite, HTTP smoke of every route, independent verifier (Risk item 6: no tests cover views) | inline + verifier | in progress (verifier running) | 11f9b61 |

## Log

- L1 (2026-11-09) user: "ajusta todo el frontend para que quede con excelente ux/ui si quieres lo reestructuras"
- L2 audit (explorer): 25 views all via layout.php; app.css 2438 lines, desktop-first, 2 breakpoints; top issues: no focus-visible, ~39 emoji icons, unlabelled inputs, tables only scroll on mobile, overloaded action rows, no active nav, color-only states, inconsistent forms/empty states, mixed EN/ES copy, small touch targets. No tests render views. `EstadoTorneo::badge()` emits badge classes.
- L3 route evidence: T2-T6 are independent view groups with disjoint files, each several files and 100+ lines → Writer trigger (a) parallelism. T1 is a prerequisite contract, written inline first.
- L4 T1 committed 23dc5f6 (design system core, shell, icons, app.js, docs/frontend.md, helpers + ViewHelpersTest; tests written alongside the helpers, not RED-first). T2-T6 writers launched in parallel with disjoint surfaces; legacy app.css still on disk but no longer loaded.
- L5 Writers T2-T6 done; each reported php -l OK, phpunit OK, curl GET smoke 200 without PHP warnings; parent spot-checked one page per unit. Core follow-ups applied by parent: pagination inline-flex + .pagination__gap, sticky form-actions above tabbar, data-print hook.
- L6 T7 seam (11f9b61): shared views/partials/torneo-tabs.php replaces 7 duplicated tab blocks (disabled tabs shown for later phases; Premios/Auditoría for managers only); Router::fallback + ErrorController render the designed 404/405 page (RouterTest RED→GREEN, suite 66 OK); legacy app.css removed; GET smoke of all routes: 200/302 expected, 0 PHP warnings; `*.csv` 404 is PHP built-in server static-file handling (pre-existing, works under Apache). Browser check: dashboard, hub, marcador in light/dark OK. Independent verifier launched (Risk item 6).
