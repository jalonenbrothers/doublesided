<?php
declare(strict_types=1);

/**
 * Markdown — safe-subset renderer (ADR-003, lab-blog pattern).
 *
 * Supported: ## / ### headings, paragraphs, **bold**, *italic*,
 * [text](https://...) links (http/https only), bullet lists (- ),
 * inline source refs [text][src-id] -> <sup> citation chips,
 * blockquotes. Raw HTML is NEVER passed through: text is escaped
 * first, then only our own known-safe tags are added back.
 */
final class Markdown
{
    /** Render a safe-subset markdown string to HTML. */
    public static function render(string $md): string
    {
        $md = str_replace("\r\n", "\n", $md);
        $lines = explode("\n", $md);
        $html = [];
        $para = [];
        $list = [];
        $quote = [];

        $flushPara = function () use (&$para, &$html): void {
            if ($para) {
                $html[] = '<p>' . self::inline(implode("\n", $para)) . '</p>';
                $para = [];
            }
        };
        $flushList = function () use (&$list, &$html): void {
            if ($list) {
                $html[] = '<ul>' . implode('', array_map(
                    fn($li) => '<li>' . self::inline($li) . '</li>', $list)) . '</ul>';
                $list = [];
            }
        };
        $flushQuote = function () use (&$quote, &$html): void {
            if ($quote) {
                $html[] = '<blockquote><p>' . self::inline(implode(' ', $quote)) . '</p></blockquote>';
                $quote = [];
            }
        };
        $flushAll = function () use ($flushPara, $flushList, $flushQuote): void {
            $flushPara();
            $flushList();
            $flushQuote();
        };

        foreach ($lines as $line) {
            if (trim($line) === '') {
                $flushAll();
                continue;
            }
            if (preg_match('/^(#{2,3})\s+(.*)$/', $line, $m) === 1) {
                $flushAll();
                $tag = strlen($m[1]) === 2 ? 'h2' : 'h3';
                $html[] = '<' . $tag . ' id="' . self::anchorId($m[2]) . '">'
                        . self::inline($m[2]) . '</' . $tag . '>';
                continue;
            }
            if (str_starts_with($line, '- ')) {
                $flushPara();
                $flushQuote();
                $list[] = trim(substr($line, 2));
                continue;
            }
            if (str_starts_with($line, '> ')) {
                $flushPara();
                $flushList();
                $quote[] = trim(substr($line, 2));
                continue;
            }
            $flushList();
            $flushQuote();
            $para[] = trim($line);
        }
        $flushAll();
        return implode("\n", $html);
    }

    /** Inline elements. Escape everything first, then re-add known-safe markup. */
    public static function inline(string $s): string
    {
        $s = htmlspecialchars($s, ENT_QUOTES, 'UTF-8');

        // links [text](http...) — http/https only, target/rel added
        $s = preg_replace_callback(
            '/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/',
            fn(array $m): string => '<a href="' . $m[2] . '" rel="noopener noreferrer" target="_blank">'
                . $m[1] . '</a>',
            $s
        ) ?? $s;

        // source refs [label][src-id] and bare [src-id] citations
        $s = preg_replace_callback(
            '/\[(?:[^\]\[]*)\]\[(src-[a-z0-9-]+)\]/',
            fn(array $m): string => '<sup class="src-ref" id="ref-' . $m[1] . '">'
                . '<a href="#source-' . $m[1] . '">' . $m[1] . '</a></sup>',
            $s
        ) ?? $s;
        $s = preg_replace_callback(
            '/(?<!\])\[(src-[a-z0-9-]+)\](?!\()/',
            fn(array $m): string => '<sup class="src-ref" id="ref-' . $m[1] . '">'
                . '<a href="#source-' . $m[1] . '">' . $m[1] . '</a></sup>',
            $s
        ) ?? $s;

        // bold, then italic
        $s = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $s) ?? $s;
        $s = preg_replace('/(^|\s)\*([^*\s][^*]*)\*/', '$1<em>$2</em>', $s) ?? $s;

        return $s;
    }

    private static function anchorId(string $heading): string
    {
        $id = strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', trim($heading)) ?? '');
        return trim($id, '-') ?: 's';
    }
}
