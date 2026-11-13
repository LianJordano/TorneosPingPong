# Torneo fiesta

Objective: standalone "Torneo fiesta" system with a roulette-style random draw revealed one by one.
Branch: `feature/torneo-fiesta` · Delivery: ask-on-risk · Runner: `vendor/bin/phpunit`

## Specs

- S1 — New section "torneo fiesta", built as a parallel system: "es armar un sistema paralelo torneo fiesta" / "se registran los participantes y se arma".
- S2 — Doubles with random teams: "armar torneos de dobles pero para darle mas intensidad los equipos se armaran aleatorio".
- S3 — Roulette reveal one by one: "con un evento donde sea como una ruleta y vaya mostrando 1 a 1 como se van armando las parejas". Order: "primero aleatoriamente salen las primeras personas , luego las parejas de esas personas" / "per todo aleatorio". Add tension: "agregando tension para que sea mas vacano".
- S4 — Formats: "puede ser eliminacion directa o por grupos".
- S5 — Also singles: "que tambien aplique para individuales".
- S6 — UX/UI: "que quede con excelente ux/ui".

Assumptions (defaults chosen by the agent, adjustable):
- A1 Participants are registered by free-text name (paste one per line), not linked to `persona`.
- A2 Doubles need an even number of participants; draw is blocked otherwise.
- A3 Groups: N groups, top K per group advance to a single-elimination bracket seeded crosswise (1A vs 2B).
- A4 Result entry is a simple score (sets or points) per side; higher score wins; no ties.
- A5 ADMIN manages fiestas and runs the draw; ADMIN/ANOTADOR load results; any logged user can watch.

## Tasks

- [x] T1 (S1-S5) Schema + migration for `fiesta*` tables · inline
- [x] T2 (S2-S5) Pure domain: `SorteoFiesta`, `TablaGrupoFiesta` + unit tests · inline
- [x] T3 (S1, S4, S5) Repository + controller + routes + nav · inline
- [x] T4 (S1, S6) Views: list, create, hub (participants, groups, bracket, results) · inline
- [x] T5 (S3, S6) Roulette stage: JS + CSS animation with tension, replay, auto/manual · inline

## Log

- L1 (2026-11-13) Original request: "quiero una funcionalidad para armar torneos de dobles pero para darle mas intensidad los equipos se armaran aleatorio, pero con un evento donde sea como una ruleta y vaya mostrando 1 a 1 como se van armando las parejas dependeiendo de cuantos sean primero salen los 1s y luego las parejas equivalentes pero agragando tension para que sea mas vacano, puede ser eliminacion directa o por grupos una nueva seccion torneo fiesta, bueno que tambien aplique para individuales que quede con excelente ux/ui"
- L2 (2026-11-13) Clarification: "osea primero aleatoriamente salen las primeras personas , luego las parejas de esas personas" / "per todo aleatorio"
- L3 (2026-11-13) Correction: "es armar un sistema paralelo torneo fiesta" / "se registran los participantes y se arma" → no reuse of `torneo`/`inscripcion`; only pure domain helpers (`BracketEliminacion`, `FixtureRoundRobin`) are reused.
- L4 (2026-11-13) Verified: `vendor/bin/phpunit` 82 tests OK (16 new). HTTP E2E on localhost: create → add names (dedupe keeps first spelling) → draw → roulette JSON → groups (tie rejected) → knockout → champion → FINALIZADO; singles 5 players → 8-slot bracket with 3 byes; odd doubles blocked; undo draw; guest redirected to /login. Browser: roulette spins, reveals, skip and end screen with no console errors. QA data deleted.
