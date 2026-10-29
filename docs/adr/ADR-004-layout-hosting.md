# ADR-004 — Repository layout and shared-hosting docroot

**Status:** accepted (2026-10-29) · **Authority:** source-spec §7.3,
reconciliation item 12

## Decision

Adopt the spec's layered layout (adapted, no `app/Application/...`
deep PSR-0 tree — house-flatter but same boundaries):

```
public/          ONLY web root: index.php front controller, assets/,
                 .htaccess (baseline + internal denies)
lib/             config.example.php/config.php, db.php (PDO factory)
src/             ContentModel.php, Importer.php, Markdown.php,
                 FrontMatter.php, Renderer.php  (house class-per-file)
bin/             import-content.php CLI (--dry-run, strict mode)
content/issues/  managed imported-content dir (operator's MD fixtures)
fixtures/        fixture-01.md — the Phase 0 sample issue
migrations/      plain SQL, numbered, idempotent + README
resources/views/ PHP templates (layout + issue views)
tests/           run.php house runner
docs/adr/        these ADRs
```

**Hostinger shared hosting cannot change docroot**, so the repo root =
subdomain web root with the stack-standard `.htaccess` baseline
(Options -Indexes, front-controller rewrite, `RedirectMatch 404` on
`/(lib|src|tests|migrations|fixtures|content|bin|docs|resources)/`,
`/.git`, `config*.php`). Every non-public dir is denied by Apache —
verified on real Apache in CI, not just the dev router (Sprint 1
lesson). `public/` still exists as front controller location for
future hosts where docroot CAN be pointed at it.

## Consequences

Two working configs verified: (a) Hostinger-style: docroot=repo root,
front controller at `public/index.php` reached via rewrite; (b) dev:
Apache docroot=public/. QA must re-verify denies under real Apache.
