# ADR-002 — MVP content types

**Status:** accepted (2026-10-29) · **Authority:** source-spec §2 (9
content types), reconciliation item on ADR-002

## Decision

The platform supports 9 content types (comparison, perspectives,
pros-cons, explainer, fact-check, timeline, decision-guide, update,
briefing) — two sides are a metaphor, not a schema constraint. For the
MVP (Phases 0–1) we implement **three** types, chosen as the most
distinct renderings of the same model:

1. `comparison` — full dual-perspective compare view (the pilot)
2. `perspectives` — side-led layout
3. `explainer` — single-column with orientation/synthesis emphasis

All three import through the identical fixture format and schema; they
differ only in template selection (`content_type` → view template).
The remaining six types are Phase 2+ templates over the same data —
no schema change required. Vertical slice (Phase 0) ships
`comparison` only.

## Consequences

Frontend gets one template per type; `content_type` is validated
against a fixed allowlist in the importer. Unknown type = reject, not
silently render.
