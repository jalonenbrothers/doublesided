# doublesided — Two Sides of the Same Coin

Reusable dual-perspective publishing platform (MVP Lab team). One PHP
codebase renders contested questions with both perspectives, claim-level
sources, shared ground and visible uncertainty. **Pilot site theme: AI
benefits / harms / governance** (ADR-005; medium risk tier — operator
sign-off mandatory before publish).

Phase 0 vertical slice: Markdown fixture in Git → validated importer →
MySQL → one issue rendered in the compare view, responsive, on real
Apache.

- docs: `docs/adr/ADR-001..005` (schema, content types, zero-Composer,
  hosting layout, pilot theme + risk tier)
- fixture format: `fixtures/fixture-01.md` (spec §9.1 shape)
- authorities: see the project tracker `docs/projects/two-sides/`
  (source-spec.md + reconciliation.md)

## Stack

LAMP: PHP 8.3+, no frameworks, no Composer (ADR-003), MySQL 8 via PDO
(sqlite for dev/tests), vanilla HTML/CSS/JS, no build steps.

## Layout

```
public/          web root: index.php front controller + assets
lib/             config.example.php, db.php
src/             FrontMatter, Markdown, Importer, ContentModel
bin/             import-content.php CLI (--dry-run)
fixtures/        fixture-01.md — sample issue
migrations/      plain SQL + operator README
resources/views/ PHP templates
tests/           run.php (sqlite) + schema.sqlite.sql
```

## Content workflow (operator)

1. Write/copy an `.md` fixture (use `fixtures/fixture-01.md` as the
   template) into `content/issues/`.
2. Validate before commit: `php bin/import-content.php content/issues/foo.md --dry-run`
   (exit 0 = valid; lists every reason otherwise).
3. Commit + push (operator approval IS the publish gate for this
   medium-risk pilot).
4. Import on the server (Hostinger SSH or ask the team):
   `php bin/import-content.php content/issues`
   Importer is idempotent (unchanged content = no-op) and never
   auto-publishes: only `status: published` content is public, drafts
   and in-review issues always 404.

## Tests

```
php tests/run.php     # 68 checks: parser, markdown, importer
                       # valid/unsafe/idempotent, HTTP feature +
                       # XSS/SQLi/traversal security set (sqlite + dev router)
```
MySQL integration mirror: 13 checks against the dev MySQL docker.

## Deploy (Hostinger subdomain)

1. Clone/upload repo to the subdomain's web root
2. `cp lib/config.example.php lib/config.php` — fill db creds from hPanel
3. Run migrations/ per migrations/README.md
4. Done — no build steps
5. First content: copy `fixtures/fixture-01.md` to `content/issues/` and
   import (see Content workflow above)

## API usage + costs

No external APIs consumed. $0 recurring; platform cost = one Hostinger
subdomain slot.

## Security notes

PDO prepared statements everywhere; output escaped at render; raw HTML
never passes the markdown renderer; source URLs http/https only; media
locations traversal-checked; internals 404 via .htaccess (verified on
real Apache, not just the dev router); canonical URLs from config only
(Host header never trusted); security headers: nosniff,
Referrer-Policy, X-Frame-Options, CSP.
