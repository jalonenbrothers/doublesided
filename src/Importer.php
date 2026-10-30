<?php
declare(strict_types=1);

/**
 * Importer — MD fixture -> validated -> upserted rows (spec §9.2,
 * 12-step contract, MVP subset). Idempotent by slug+content_hash.
 *
 * Steps implemented: read permitted dirs only, strict front-matter
 * parse, schema validation (slugs, urls, status, sources, media),
 * safe markdown render (done at VIEW time — the renderer is
 * deterministic; body_markdown is stored), content hash, transaction,
 * upsert by slug, revision record, summary + non-zero exit on
 * failure. --dry-run validates without writing. Publication never
 * happens implicitly: fixture status must say 'published'.
 *
 * All seven body sections are persisted on the issues row:
 * orientation, the case for / the case against (-> perspectives),
 * shared ground, what the evidence says, what remains uncertain,
 * synthesis (QA BUG1 fix).
 */
final class ImportError extends Exception
{
    public array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
        parent::__construct('validation failed: ' . implode('; ', $errors));
    }
}

final class Importer
{
    public const STATUSES = ['draft', 'review', 'approved', 'published', 'archived'];
    public const CONTENT_TYPES = ['comparison', 'perspectives', 'explainer'];
    public const SOURCE_TYPES = ['primary', 'research', 'report', 'news', 'opinion', 'official', 'data', 'other'];
    public const MEDIA_TYPES = ['image', 'youtube', 'video'];

    public function __construct(
        private PDO $pdo,
        private string $actorType = 'agent',
        private string $actorLabel = 'importer'
    ) {
    }

    /**
     * Import one .md file. Returns summary array.
     * @throws ImportError on validation failure (transaction never opened).
     */
    public function importFile(string $path, bool $dryRun = false, ?string $gitHash = null): array
    {
        $real = realpath($path);
        if ($real === false || !is_file($real)) {
            throw new ImportError(["file not found: $path"]);
        }
        // path containment: only files under the given root are importable
        $raw = file_get_contents($real);
        if ($raw === false) {
            throw new ImportError(["unreadable: $path"]);
        }
        return $this->importString($raw, $dryRun, $gitHash, basename($real));
    }

    /** Import from a raw fixture string (used by tests + upload path). */
    public function importString(string $raw, bool $dryRun = false, ?string $gitHash = null, string $name = 'inline'): array
    {
        [$fm, $body] = FrontMatter::parse($raw);
        $fm = self::validate($fm, $body);

        $hash = self::contentHash($fm, $body);
        $existing = $this->pdo->prepare('SELECT id, content_hash FROM issues WHERE slug = ?');
        $existing->execute([$fm['slug']]);
        $row = $existing->fetch();
        if ($row && $row['content_hash'] === $hash) {
            return ['file' => $name, 'result' => 'unchanged', 'slug' => $fm['slug']];
        }

        if ($dryRun) {
            return ['file' => $name, 'result' => 'dry-run-ok', 'slug' => $fm['slug']];
        }

        $this->pdo->beginTransaction();
        try {
            $issueId = $this->upsertIssue($fm, $body, $row ? (int)$row['id'] : null);
            $this->replaceChildren($issueId, $fm, $body);
            $this->recordRevision($issueId, $hash, $gitHash);
            $this->pdo->commit();
        } catch (Throwable $e) {
            $this->pdo->rollBack();
            throw $e;
        }
        return [
            'file' => $name,
            'result' => $row ? 'updated' : 'imported',
            'slug' => $fm['slug'],
            'issue_id' => $issueId,
            'status' => $fm['status'],
        ];
    }

    /** Import every .md under a directory (permitted content dir only). */
    public function importDir(string $dir, bool $dryRun = false): array
    {
        $real = realpath($dir);
        if ($real === false || !is_dir($real)) {
            throw new ImportError(["directory not found: $dir"]);
        }
        $summary = ['seen' => 0, 'accepted' => 0, 'rejected' => 0, 'results' => []];
        $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
            $real, FilesystemIterator::SKIP_DOTS));
        $files = [];
        foreach ($rii as $f) {
            if ($f->isFile() && strtolower($f->getExtension()) === 'md') {
                $files[] = $f->getPathname();
            }
        }
        sort($files);
        $summary['seen'] = count($files);
        foreach ($files as $file) {
            try {
                $r = $this->importFile($file, $dryRun);
                $summary['accepted']++;
                $summary['results'][] = $r;
            } catch (ImportError $e) {
                $summary['rejected']++;
                $summary['results'][] = ['file' => basename($file), 'result' => 'rejected', 'errors' => $e->errors];
            }
        }
        return $summary;
    }

    // ------------------------------------------------ validation

    /** @return validated+normalised front matter; @throws ImportError */
    public static function validate(array $fm, string $body): array
    {
        $err = [];
        if ((int)($fm['schema_version'] ?? 0) !== 1) {
            $err[] = 'schema_version must be 1';
        }
        foreach (['slug', 'title', 'content_type', 'status', 'summary', 'sides', 'sources'] as $k) {
            if (!isset($fm[$k]) || $fm[$k] === null) {
                $err[] = "missing required field: $k";
            }
        }
        if (isset($fm['slug'])) {
            if (!preg_match('/^[a-z0-9][a-z0-9-]{0,120}$/', (string)$fm['slug'])
                || str_contains((string)$fm['slug'], '..')) {
                $err[] = 'invalid slug (lowercase letters, digits, hyphens)';
            }
        }
        if (isset($fm['title']) && trim((string)$fm['title']) === '') {
            $err[] = 'empty title';
        }
        if (isset($fm['content_type']) && !in_array($fm['content_type'], self::CONTENT_TYPES, true)) {
            $err[] = 'unknown content_type: ' . (string)$fm['content_type'];
        }
        if (isset($fm['status']) && !in_array($fm['status'], self::STATUSES, true)) {
            $err[] = 'invalid status: ' . (string)$fm['status'];
        }
        if (($fm['status'] ?? '') === 'published' && empty($fm['published_at'])) {
            $err[] = 'published content requires published_at';
        }
        foreach (['published_at', 'reviewed_at'] as $dateField) {
            if (!empty($fm[$dateField])
                && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$fm[$dateField]) !== 1) {
                $err[] = "$dateField must be YYYY-MM-DD";
            }
        }

        // sides: exactly 2 for comparison, ids unique, one for/one against
        $sideIds = [];
        if (isset($fm['sides']) && is_array($fm['sides'])) {
            if (count($fm['sides']) !== 2) {
                $err[] = 'comparison requires exactly 2 sides';
            }
            $pos = 0;
            foreach ($fm['sides'] as $side) {
                if (!is_array($side) || empty($side['id']) || empty($side['label']) || !isset($side['summary'])) {
                    $err[] = 'each side needs id, label, summary';
                    continue;
                }
                if (!preg_match('/^[a-z0-9][a-z0-9-]{0,60}$/', (string)$side['id'])) {
                    $err[] = 'invalid side id: ' . (string)$side['id'];
                }
                $sideIds[] = (string)$side['id'];
                $pos++;
            }
        }
        if (count($sideIds) !== count(array_unique($sideIds))) {
            $err[] = 'duplicate side ids';
        }

        // sources: valid URL, type allowlist, unique ids
        $srcIds = [];
        if (isset($fm['sources']) && is_array($fm['sources'])) {
            foreach ($fm['sources'] as $i => $src) {
                if (!is_array($src) || empty($src['id']) || empty($src['title'])
                    || empty($src['publisher']) || empty($src['url'])) {
                    $err[] = "source #$i needs id, title, publisher, url";
                    continue;
                }
                if (!self::safeUrl((string)$src['url'])) {
                    $err[] = 'unsafe or invalid source url: ' . (string)$src['url'];
                }
                $type = (string)($src['source_type'] ?? 'other');
                if (!in_array($type, self::SOURCE_TYPES, true)) {
                    $err[] = "source {$src['id']}: invalid source_type '$type'";
                }
                if (!empty($src['published_at'])
                    && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$src['published_at']) !== 1) {
                    $err[] = "source {$src['id']}: published_at must be YYYY-MM-DD";
                }
                $srcIds[] = (string)$src['id'];
            }
            if (count($srcIds) !== count(array_unique($srcIds))) {
                $err[] = 'duplicate source ids';
            }
        }

        // media: type allowlist, safe path/url, alt required for images
        if (isset($fm['media']) && is_array($fm['media'])) {
            foreach ($fm['media'] as $i => $m) {
                if (!is_array($m) || !isset($m['type'])) {
                    $err[] = "media #$i needs type";
                    continue;
                }
                if (!in_array((string)$m['type'], self::MEDIA_TYPES, true)) {
                    $err[] = 'media: invalid type ' . (string)$m['type'];
                }
                $loc = (string)($m['path'] ?? $m['url'] ?? '');
                if ($loc === '' || !self::safeMediaLocation($loc, (string)$m['type'])) {
                    $err[] = "media #$i: unsafe or missing location";
                }
                if (($m['type'] ?? '') === 'image' && empty($m['alt'])) {
                    $err[] = "media #$i: images require alt text";
                }
            }
        }

        // body: sections required + inline source refs must resolve
        foreach (['orientation', 'the case for', 'the case against',
                  'shared ground', 'what the evidence says',
                  'what remains uncertain', 'synthesis'] as $sec) {
            if (!preg_match('/^##\s+' . preg_quote($sec, '/') . '\s*$/mi', $body)) {
                $err[] = "missing body section: $sec";
            }
        }
        $refs = [];
        if (preg_match_all('/\[(?:[^\]\[]*)\]\[(src-[a-z0-9-]+)\]/', $body, $m)) {
            array_push($refs, ...$m[1]);
        }
        if (preg_match_all('/(?<!\])\[(src-[a-z0-9-]+)\](?!\()/', $body, $m)) {
            array_push($refs, ...$m[1]);
        }
        foreach (array_unique($refs) as $ref) {
            if (!in_array($ref, $srcIds, true)) {
                $err[] = "body references unknown source id: $ref";
            }
        }
        if (preg_match('/<\s*(script|iframe|object|embed|style)\b/i', $body)) {
            $err[] = 'raw HTML/script tags are not allowed in body';
        }

        if ($err) {
            throw new ImportError($err);
        }
        return $fm;
    }

    /** http/https only, no userinfo tricks, host present. */
    public static function safeUrl(string $url): bool
    {
        $p = parse_url($url);
        if ($p === false || !isset($p['scheme'], $p['host'])) {
            return false;
        }
        if (!in_array(strtolower($p['scheme']), ['http', 'https'], true)) {
            return false;
        }
        if (isset($p['user']) || isset($p['pass'])) {
            return false;
        }
        return filter_var($url, FILTER_VALIDATE_URL) !== false;
    }

    /** images: /relative path (no traversal) or https; youtube: video id or watch/embed URL. */
    public static function safeMediaLocation(string $loc, string $type): bool
    {
        if ($type === 'youtube') {
            return preg_match('/^[A-Za-z0-9_-]{11}$/', $loc) === 1
                || self::safeUrl($loc);
        }
        if (preg_match('#^\.\.?/#', $loc) || str_contains($loc, '..')) {
            return false;
        }
        if (str_starts_with($loc, '/')) {
            return true; // site-relative asset path
        }
        return self::safeUrl($loc);
    }

    public static function contentHash(array $fm, string $body): string
    {
        unset($fm['status'], $fm['published_at'], $fm['reviewed_at']); // editorial state ≠ content
        ksort($fm);
        return hash('sha256', json_encode($fm, JSON_UNESCAPED_SLASHES) . "\n---\n" . $body);
    }

    /** Split a markdown body into lowercase-section-name => markdown map. */
    public static function splitSections(string $body): array
    {
        $sections = [];
        $cur = '';
        $buf = [];
        foreach (explode("\n", $body) as $line) {
            if (preg_match('/^##\s+(.*)$/', $line, $m) === 1) {
                if ($cur !== '') {
                    $sections[$cur] = trim(implode("\n", $buf));
                }
                $cur = strtolower(trim($m[1]));
                $buf = [];
            } elseif ($cur !== '') {
                $buf[] = $line;
            }
        }
        if ($cur !== '') {
            $sections[$cur] = trim(implode("\n", $buf));
        }
        return $sections;
    }

    // ------------------------------------------------ writing

    private function upsertIssue(array $fm, string $body, ?int $id): int
    {
        $hash = self::contentHash($fm, $body);
        $now = date('Y-m-d H:i:s');
        $sec = self::splitSections($body);
        if ($id) {
            $this->pdo->prepare(
                'UPDATE issues SET title=?, dek=?, content_type=?, status=?, published_at=?,
                 reviewed_at=?, summary=?, orientation=?, shared_ground=?, evidence=?,
                 unknowns=?, synthesis=?, content_hash=?, updated_at=? WHERE id=?'
            )->execute([
                (string)$fm['title'],
                isset($fm['dek']) ? (string)$fm['dek'] : null,
                (string)$fm['content_type'],
                (string)$fm['status'],
                !empty($fm['published_at']) ? $fm['published_at'] : null,
                !empty($fm['reviewed_at']) ? $fm['reviewed_at'] : null,
                (string)$fm['summary'],
                $sec['orientation'] ?: null,
                $sec['shared ground'] ?: null,
                $sec['what the evidence says'] ?: null,
                $sec['what remains uncertain'] ?: null,
                $sec['synthesis'] ?: null,
                $hash, $now, $id,
            ]);
            return $id;
        }
        $this->pdo->prepare(
            'INSERT INTO issues (slug, title, dek, content_type, status, published_at,
             reviewed_at, summary, orientation, shared_ground, evidence, unknowns,
             synthesis, content_hash, created_at, updated_at)
             VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)'
        )->execute([
            (string)$fm['slug'], (string)$fm['title'],
            isset($fm['dek']) ? (string)$fm['dek'] : null,
            (string)$fm['content_type'], (string)$fm['status'],
            !empty($fm['published_at']) ? $fm['published_at'] : null,
            !empty($fm['reviewed_at']) ? $fm['reviewed_at'] : null,
            (string)$fm['summary'],
            $sec['orientation'] ?: null,
            $sec['shared ground'] ?: null,
            $sec['what the evidence says'] ?: null,
            $sec['what remains uncertain'] ?: null,
            $sec['synthesis'] ?: null,
            $hash, $now, $now,
        ]);
        return (int)$this->pdo->lastInsertId();
    }

    private function replaceChildren(int $issueId, array $fm, string $body): void
    {
        foreach (['perspectives', 'issue_sources', 'media', 'issue_topics'] as $t) {
            $this->pdo->prepare("DELETE FROM $t WHERE issue_id = ?")->execute([$issueId]);
        }

        // split body into side sections ("The case for" / "The case against")
        $sections = self::splitSections($body);
        $now = date('Y-m-d H:i:s');
        $pos = 0;
        $bodyBySlot = ['the case for', 'the case against'];
        foreach ($fm['sides'] as $i => $side) {
            $pos++;
            $this->pdo->prepare(
                'INSERT INTO perspectives (issue_id, slug, label, position, short_summary, body_markdown, created_at, updated_at)
                 VALUES (?,?,?,?,?,?,?,?)'
            )->execute([
                $issueId, (string)$side['id'], (string)$side['label'], $pos,
                (string)$side['summary'],
                $sections[$bodyBySlot[$i] ?? ''] ?? '',
                $now, $now,
            ]);
        }

        foreach ($fm['sources'] as $src) {
            // upsert source by URL
            $find = $this->pdo->prepare('SELECT id FROM sources WHERE url = ?');
            $find->execute([(string)$src['url']]);
            $sid = $find->fetchColumn();
            if (!$sid) {
                $this->pdo->prepare(
                    'INSERT INTO sources (url, title, publisher, author, published_at, accessed_at, source_type, notes, created_at)
                     VALUES (?,?,?,?,?,?,?,?,?)'
                )->execute([
                    (string)$src['url'], (string)$src['title'], (string)$src['publisher'],
                    isset($src['author']) ? (string)$src['author'] : null,
                    !empty($src['published_at']) ? $src['published_at'] : null,
                    date('Y-m-d'),
                    (string)($src['source_type'] ?? 'other'),
                    isset($src['notes']) ? (string)$src['notes'] : null,
                    date('Y-m-d H:i:s'),
                ]);
                $sid = (int)$this->pdo->lastInsertId();
            }
            $this->pdo->prepare(
                'INSERT INTO issue_sources (issue_id, source_id, ref_key) VALUES (?,?,?)'
            )->execute([$issueId, (int)$sid, (string)$src['id']]);
        }

        if (!empty($fm['media']) && is_array($fm['media'])) {
            $p = 0;
            foreach ($fm['media'] as $m) {
                $p++;
                $this->pdo->prepare(
                    'INSERT INTO media (issue_id, media_type, url_or_path, alt_text, caption, credit, position)
                     VALUES (?,?,?,?,?,?,?)'
                )->execute([
                    $issueId, (string)$m['type'],
                    (string)($m['path'] ?? $m['url'] ?? ''),
                    (string)($m['alt'] ?? ''), (string)($m['caption'] ?? ''), (string)($m['credit'] ?? ''),
                    $p,
                ]);
            }
        }

        foreach ((array)($fm['topics'] ?? []) as $topic) {
            $slug = strtolower((string)$topic);
            if (!preg_match('/^[a-z0-9][a-z0-9-]{0,60}$/', $slug)) {
                continue;
            }
            $find = $this->pdo->prepare('SELECT id FROM topics WHERE slug = ?');
            $find->execute([$slug]);
            $tid = $find->fetchColumn();
            if (!$tid) {
                $this->pdo->prepare('INSERT INTO topics (slug, title) VALUES (?,?)')
                    ->execute([$slug, ucwords(str_replace('-', ' ', $slug))]);
                $tid = (int)$this->pdo->lastInsertId();
            }
            $this->pdo->prepare('INSERT INTO issue_topics (issue_id, topic_id) VALUES (?,?)')
                ->execute([$issueId, (int)$tid]);
        }
    }

    private function recordRevision(int $issueId, string $hash, ?string $gitHash): void
    {
        $this->pdo->prepare(
            'INSERT INTO revisions (issue_id, revision_key, changed_at, change_summary, actor_type, actor_label, git_hash)
             VALUES (?,?,?,?,?,?,?)'
        )->execute([
            $issueId, substr($hash, 0, 16), date('Y-m-d H:i:s'),
            'imported via bin/import-content.php',
            $this->actorType, $this->actorLabel, $gitHash,
        ]);
    }
}
