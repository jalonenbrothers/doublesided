# ADR-005 — Pilot site theme and risk tier

**Status:** accepted (2026-10-29) · **Authority:** source-spec §16.1-B
+ §17, reconciliation item 9, task body (operator-selected)

## Decision

Pilot site theme: **AI benefits / harms / governance** (source-spec
§16.1-B). Why: the newsroom archive provides ready content; the theme
dogfoods the AI-authoring loop immediately; it is the strongest
platform demonstration (evidence tiers, uncertainty, shared ground all
matter for AI debates).

**Risk tier: MEDIUM** (per §17): AI's societal impact is contested,
fast-moving, and reputationally sensitive. Consequences:

- Operator sign-off is **mandatory before any publish** — the existing
  operator gate IS the sign-off mechanism (reconciliation item 3).
- Evidence review required: every material claim links a source;
  sources carry publication dates; stale sources (>) flagged in QA.
- No auto-publish, ever, on this site: importer accepts MD only into
  draft/review; `status: published` requires explicit fixture state
  AND operator approval of the deploy.
- Corrections: visible correction log (revisions) on published issues.

Pilot subdomain (devops to confirm): `twosides`-pattern Hostinger
subdomain. Low-risk themes (consumer/tech decisions, §16-F) may later
run on the same codebase with lighter review.

## Consequences

Content in `content/issues/` on the pilot site is operator-approved
before the git push that deploys it — the deploy IS the approval.
Phase 4's upload-inbox path gets the same gate.
