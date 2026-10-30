-- doublesided migration 002 — persist all compare body sections (QA BUG1 fix)
-- Adds the body-section columns the importer now fills:
--   orientation, evidence, synthesis  (shared_ground + unknowns exist from 001)
-- Safe to re-run: NO — MySQL has no ADD COLUMN IF NOT EXISTS. If you re-run it,
-- MySQL errors with "Duplicate column name" which is harmless; the columns are
-- already there. Run once on a database that has had 001 applied.

ALTER TABLE issues ADD COLUMN orientation MEDIUMTEXT NULL AFTER summary;
ALTER TABLE issues ADD COLUMN evidence    MEDIUMTEXT NULL AFTER shared_ground;
ALTER TABLE issues ADD COLUMN synthesis   MEDIUMTEXT NULL AFTER unknowns;
