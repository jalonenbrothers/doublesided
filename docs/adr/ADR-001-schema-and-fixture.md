# ADR-001 — Content model: schema v1 and fixture format

**Status:** accepted (2026-10-29) · **Authority:** source-spec §8 + §9.1,
reconciliation.md items 1–3

## Decision

Schema v1 = source-spec §8.1 core tables, **minus premature parts** per
spec §8.3 and reconciliation:

```
issues        id, slug (unique), title, dek, content_type, status,
              published_at, reviewed_at, updated_at, summary,
              shared_ground, unknowns, content_hash (CHAR 64),
              created_at
perspectives  id, issue_id, slug, label, position, short_summary,
              body_markdown, created_at, updated_at
sources       id, url (unique after normalisation), title, publisher,
              author, published_at, accessed_at, source_type, notes
issue_sources id, issue_id, source_id, ref_key   (front-matter source
              ids resolved here; body cites reference the ref_key)
media         id, issue_id, media_type, url_or_path, alt_text, caption,
              credit, position
topics, issue_topics        normalised topics + M2M
revisions     id, issue_id, revision_key, changed_at, change_summary,
              actor_type, actor_label, git_hash
imports       id, run_at, files_seen, accepted, rejected, notes
```

Deferred (later phases, per §8.3): `claims`, `claim_sources`,
`confidence_label` as columns, `tags/issue_tags` (topics cover Phase 0),
`users`/`editorial_audit_log` (no auth yet). Claims START as structured
front-matter/summary text rendered by the importer; **source URLs,
publication dates, attribution, review state and revision history are
NOT negotiable** and are structured from day one (`sources`,
`issue_sources`, `revisions`, `issues.status/reviewed_at`).

Content-hash: SHA-256 over slug + front-matter canonical serialisation +
body markdown. Upsert by slug; unchanged hash = no-op (idempotent).
Changed hash = new revision row.

## Fixture format

Source-spec §9.1 YAML front-matter example, verbatim shape:
`schema_version, slug, title, content_type, status, published_at,
reviewed_at, topics[], summary, sides[] (id/label/summary),
sources[] (id/title/publisher/url/published_at/source_type),
media[] (type/path/alt/caption/credit)` + body sections:
Orientation / The case for / The case against / Shared ground /
What the evidence says / What remains uncertain / Synthesis.

Side bodies map to `perspectives` by `sides[].id` (side-A body =
"The case for", side-B body = "The case against"); shared_ground and
unknowns are structured columns; remaining sections are part of the
issue body rendered below the comparison.

Status model (binding): draft → review → approved → published →
archived. Importer NEVER auto-publishes; only `status: published` in
the fixture makes content public, and publication of medium-risk-tier
content additionally requires the operator sign-off gate (ADR-005).
