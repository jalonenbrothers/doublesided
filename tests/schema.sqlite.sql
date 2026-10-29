-- sqlite translation of migrations/001_two_sides.sql (test/dev only;
-- MySQL remains the production schema). Applied by tests/run.php.
CREATE TABLE IF NOT EXISTS issues (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL,
  dek TEXT,
  content_type TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'draft',
  published_at TEXT,
  reviewed_at TEXT,
  canonical_url TEXT,
  summary TEXT NOT NULL,
  shared_ground TEXT,
  unknowns TEXT,
  content_hash TEXT NOT NULL,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_issues_status_pub ON issues (status, published_at);

CREATE TABLE IF NOT EXISTS perspectives (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_id INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
  slug TEXT NOT NULL,
  label TEXT NOT NULL,
  position INTEGER NOT NULL,
  short_summary TEXT NOT NULL,
  body_markdown TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
CREATE INDEX IF NOT EXISTS idx_perspectives_issue ON perspectives (issue_id, position);

CREATE TABLE IF NOT EXISTS sources (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  url TEXT NOT NULL UNIQUE,
  canonical_url TEXT,
  title TEXT NOT NULL,
  publisher TEXT NOT NULL,
  author TEXT,
  published_at TEXT,
  accessed_at TEXT NOT NULL,
  source_type TEXT NOT NULL DEFAULT 'other',
  notes TEXT,
  created_at TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS issue_sources (
  issue_id INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
  source_id INTEGER NOT NULL REFERENCES sources(id),
  ref_key TEXT NOT NULL,
  PRIMARY KEY (issue_id, source_id)
);

CREATE TABLE IF NOT EXISTS media (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_id INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
  media_type TEXT NOT NULL,
  url_or_path TEXT NOT NULL,
  alt_text TEXT NOT NULL DEFAULT '',
  caption TEXT,
  credit TEXT,
  position INTEGER NOT NULL DEFAULT 1,
  width INTEGER,
  height INTEGER
);

CREATE TABLE IF NOT EXISTS topics (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  slug TEXT NOT NULL UNIQUE,
  title TEXT NOT NULL
);

CREATE TABLE IF NOT EXISTS issue_topics (
  issue_id INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
  topic_id INTEGER NOT NULL REFERENCES topics(id) ON DELETE CASCADE,
  PRIMARY KEY (issue_id, topic_id)
);

CREATE TABLE IF NOT EXISTS revisions (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  issue_id INTEGER NOT NULL REFERENCES issues(id) ON DELETE CASCADE,
  revision_key TEXT NOT NULL,
  changed_at TEXT NOT NULL,
  change_summary TEXT,
  actor_type TEXT NOT NULL,
  actor_label TEXT NOT NULL,
  git_hash TEXT
);
CREATE INDEX IF NOT EXISTS idx_revisions_issue ON revisions (issue_id, changed_at);

CREATE TABLE IF NOT EXISTS imports (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  run_at TEXT NOT NULL,
  files_seen INTEGER NOT NULL DEFAULT 0,
  accepted INTEGER NOT NULL DEFAULT 0,
  rejected INTEGER NOT NULL DEFAULT 0,
  notes TEXT
);
