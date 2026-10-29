-- doublesided migration 001 — schema v1 (ADR-001)
-- Creates: issues, perspectives, sources, issue_sources, media,
--          topics, issue_topics, revisions, imports
-- Safe to re-run: yes (CREATE TABLE IF NOT EXISTS / CREATE INDEX IF NOT EXISTS)

CREATE TABLE IF NOT EXISTS issues (
  id            BIGINT AUTO_INCREMENT PRIMARY KEY,
  slug          VARCHAR(160) NOT NULL,
  title         VARCHAR(255) NOT NULL,
  dek            TEXT NULL,
  content_type  VARCHAR(32)  NOT NULL,
  status        VARCHAR(16)  NOT NULL DEFAULT 'draft',
  published_at  DATE NULL,
  reviewed_at   DATE NULL,
  canonical_url VARCHAR(500) NULL,
  summary       TEXT NOT NULL,
  shared_ground MEDIUMTEXT NULL,
  unknowns      MEDIUMTEXT NULL,
  content_hash  CHAR(64) NOT NULL,
  created_at    DATETIME NOT NULL,
  updated_at    DATETIME NOT NULL,
  UNIQUE KEY uq_issues_slug (slug),
  KEY idx_issues_status_pub (status, published_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS perspectives (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  issue_id       BIGINT NOT NULL,
  slug           VARCHAR(80) NOT NULL,
  label          VARCHAR(160) NOT NULL,
  position       INT NOT NULL,
  short_summary  TEXT NOT NULL,
  body_markdown  MEDIUMTEXT NULL,
  created_at     DATETIME NOT NULL,
  updated_at     DATETIME NOT NULL,
  KEY idx_perspectives_issue (issue_id, position),
  CONSTRAINT fk_perspectives_issue FOREIGN KEY (issue_id)
    REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS sources (
  id           BIGINT AUTO_INCREMENT PRIMARY KEY,
  url          VARCHAR(750) NOT NULL,
  canonical_url VARCHAR(750) NULL,
  title        VARCHAR(400) NOT NULL,
  publisher    VARCHAR(255) NOT NULL,
  author       VARCHAR(255) NULL,
  published_at DATE NULL,
  accessed_at  DATE NOT NULL,
  source_type  VARCHAR(32) NOT NULL DEFAULT 'other',
  notes        TEXT NULL,
  created_at   DATETIME NOT NULL,
  UNIQUE KEY uq_sources_url (url(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_sources (
  issue_id   BIGINT NOT NULL,
  source_id  BIGINT NOT NULL,
  ref_key    VARCHAR(80) NOT NULL,
  PRIMARY KEY (issue_id, source_id),
  KEY idx_issue_sources_ref (issue_id, ref_key),
  CONSTRAINT fk_is_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_is_source FOREIGN KEY (source_id) REFERENCES sources(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS media (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  issue_id    BIGINT NOT NULL,
  media_type  VARCHAR(16) NOT NULL,
  url_or_path VARCHAR(750) NOT NULL,
  alt_text    VARCHAR(500) NOT NULL DEFAULT '',
  caption     VARCHAR(500) NULL,
  credit      VARCHAR(255) NULL,
  position    INT NOT NULL DEFAULT 1,
  width       INT NULL,
  height      INT NULL,
  KEY idx_media_issue (issue_id, position),
  CONSTRAINT fk_media_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS topics (
  id    BIGINT AUTO_INCREMENT PRIMARY KEY,
  slug  VARCHAR(80) NOT NULL,
  title VARCHAR(160) NOT NULL,
  UNIQUE KEY uq_topics_slug (slug)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS issue_topics (
  issue_id BIGINT NOT NULL,
  topic_id BIGINT NOT NULL,
  PRIMARY KEY (issue_id, topic_id),
  CONSTRAINT fk_it_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE,
  CONSTRAINT fk_it_topic FOREIGN KEY (topic_id) REFERENCES topics(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS revisions (
  id             BIGINT AUTO_INCREMENT PRIMARY KEY,
  issue_id       BIGINT NOT NULL,
  revision_key   VARCHAR(32) NOT NULL,
  changed_at     DATETIME NOT NULL,
  change_summary VARCHAR(500) NULL,
  actor_type     VARCHAR(16) NOT NULL,
  actor_label    VARCHAR(120) NOT NULL,
  git_hash       CHAR(40) NULL,
  KEY idx_revisions_issue (issue_id, changed_at),
  CONSTRAINT fk_revisions_issue FOREIGN KEY (issue_id) REFERENCES issues(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS imports (
  id          BIGINT AUTO_INCREMENT PRIMARY KEY,
  run_at      DATETIME NOT NULL,
  files_seen  INT NOT NULL DEFAULT 0,
  accepted    INT NOT NULL DEFAULT 0,
  rejected    INT NOT NULL DEFAULT 0,
  notes       TEXT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
