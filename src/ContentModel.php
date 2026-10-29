<?php
declare(strict_types=1);

/**
 * ContentModel — queries for published issues (public routes see ONLY
 * status='published'; drafts/review stay invisible, missing slug = 404).
 */
final class ContentModel
{
    /** Fetch one published issue with perspectives, or null. */
    public static function publishedIssue(string $slug): ?array
    {
        $row = ds_q(
            'SELECT * FROM issues WHERE slug = ? AND status = ?',
            [$slug, 'published']
        )->fetch();
        if (!$row) {
            return null;
        }
        $row['perspectives'] = ds_q(
            'SELECT * FROM perspectives WHERE issue_id = ? ORDER BY position',
            [(int)$row['id']]
        )->fetchAll();
        $row['sources'] = ds_q(
            'SELECT s.*, i.ref_key FROM issue_sources i
             JOIN sources s ON s.id = i.source_id
             WHERE i.issue_id = ? ORDER BY s.id',
            [(int)$row['id']]
        )->fetchAll();
        $row['topics'] = ds_q(
            'SELECT t.slug, t.title FROM issue_topics it
             JOIN topics t ON t.id = it.topic_id
             WHERE it.issue_id = ? ORDER BY t.slug',
            [(int)$row['id']]
        )->fetchAll();
        $row['media'] = ds_q(
            'SELECT media_type, url_or_path, alt_text, caption, credit
             FROM media WHERE issue_id = ? ORDER BY position',
            [(int)$row['id']]
        )->fetchAll();
        return $row;
    }

    /** Latest published issues for the home page. */
    public static function latest(int $limit = 20): array
    {
        return ds_q(
            'SELECT id, slug, title, dek, summary, content_type, published_at
             FROM issues WHERE status = ?
             ORDER BY published_at DESC, id DESC LIMIT ' . max(1, min(50, $limit)),
            ['published']
        )->fetchAll();
    }

    /** Split body sections into a named map (Orientation, Synthesis, ...). */
    public static function bodySections(array $issue, string $bodyMd): array
    {
        $sections = [];
        $current = '';
        $buf = [];
        foreach (explode("\n", $bodyMd) as $line) {
            if (preg_match('/^##\s+(.*)$/', $line, $m) === 1) {
                if ($current !== '') {
                    $sections[$current] = trim(implode("\n", $buf));
                }
                $current = strtolower(trim($m[1]));
                $buf = [];
            } elseif ($current !== '') {
                $buf[] = $line;
            }
        }
        if ($current !== '') {
            $sections[$current] = trim(implode("\n", $buf));
        }
        return $sections;
    }
}
