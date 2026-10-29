# doublesided — migrations

Plain SQL, numbered in run order. MySQL (Hostinger hPanel / phpMyAdmin).
Dev alternative: tests/run.php applies `tests/schema.sqlite.sql` to a
throwaway sqlite file — no MySQL needed locally.

## How to run (operator)

1. Open **hPanel → Databases → phpMyAdmin** for the subdomain's database.
2. Click the **SQL** tab (or Import tab), paste the contents of each file
   below **in order**, run.
3. Every file is **safe to re-run** (`CREATE TABLE IF NOT EXISTS` only).
4. Then run the content import once per new/changed fixture:
   `php bin/import-content.php content/issues`
   (shell access) — or ask the team to run it before pushing.

## Files

| # | File | What it creates | Re-run safe |
|---|------|-----------------|-------------|
| 1 | `001_two_sides.sql` | `issues`, `perspectives`, `sources`, `issue_sources`, `media`, `topics`, `issue_topics`, `revisions`, `imports` | yes |

CLI alternative (if you have shell access):
```
mysql -u USER -p DBNAME < migrations/001_two_sides.sql
```
