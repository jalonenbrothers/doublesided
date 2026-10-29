# ADR-003 — Zero-Composer divergence (house rule wins)

**Status:** accepted (2026-10-29) · **Authority:** reconciliation.md
"Where the HOUSE WINS" item 1; spec §7.1 allows Composer

## Decision

No Composer, no third-party PHP packages, in the shipped app. Diverging
from source-spec §7.1 ("maintained parsers via Composer"), we use:

- **Front-matter parser**: hand-rolled safe-subset YAML parser
  (proven in lab-blog ContentStore, 40 tests) — supports the exact
  fixture shape: flat keys, nested lists of maps, block scalars for
  long strings. Strict: unknown syntax = reject.
- **Markdown renderer**: hand-rolled safe-subset renderer (proven in
  lab-blog): headings, paragraphs, bold/italic, links (http/https
  only), lists, blockquotes, inline source refs `[label][src-id]`.
  Raw HTML/JS never passes through.
- **Testing**: `php tests/run.php` house runner (no PHPUnit).
- **Static analysis**: `php -l` on all files + intelephense in the
  editor. No PHPStan in CI until a need is proven.

## Revisit trigger

If (a) fixture format grows to need full YAML (anchors, multi-doc,
flow syntax) or (b) a security-relevant parser bug appears, revisit
and adopt a maintained parser via Composer — recorded as a new ADR
superseding this one. Until then: zero-dep.

## Consequences

Hostinger shared hosting needs no Composer at deploy time (matches
"no build steps" house rule); attack surface limited to our own
~700-line parser/renderer which is fully under our test control.
