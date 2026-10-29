<?php
declare(strict_types=1);

/**
 * FrontMatter — strict safe-subset YAML front-matter parser (ADR-003).
 *
 * Supports exactly what the fixture format (spec §9.1 / ADR-001) uses:
 *   - flat scalar keys (key: value)
 *   - block scalars (key: | or > ... indented lines) — NOT used by our
 *     fixtures but tolerated for summary/notes
 *   - nested lists of maps:
 *       key:
 *         - id: x
 *           label: y
 *     and flat lists:
 *       key:
 *         - item
 *   - quoted scalars ("..." or '...')
 * Anything else (tabs in indentation, anchors, flow syntax, unknown
 * constructs) throws FrontMatterError — strict, never permissive.
 */
final class FrontMatterError extends Exception
{
}

final class FrontMatter
{
    /** Split a file into [frontMatterArray, bodyMarkdown]. */
    public static function parse(string $raw): array
    {
        $raw = str_replace("\r\n", "\n", $raw);
        if (!str_starts_with($raw, "---\n")) {
            throw new FrontMatterError('file must start with --- front-matter delimiter');
        }
        $end = strpos($raw, "\n---\n", 4);
        if ($end === false) {
            throw new FrontMatterError('missing closing --- delimiter');
        }
        $fmText = substr($raw, 4, $end - 4);
        $body = ltrim(substr($raw, $end + 5), "\n");
        return [self::parseBlock(explode("\n", $fmText)), $body];
    }

    /** Parse a list of lines into a key=>value tree. */
    public static function parseBlock(array $lines): array
    {
        $out = [];
        $i = 0;
        $n = count($lines);
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '' || str_starts_with(trim($line), '#')) {
                $i++;
                continue;
            }
            $indent = strlen($line) - strlen(ltrim($line));
            if ($indent !== 0) {
                throw new FrontMatterError('unexpected indentation at line: ' . trim($line));
            }
            if (preg_match('/^([A-Za-z0-9_]+):\s*(.*)$/', $line, $m) !== 1) {
                throw new FrontMatterError('unparseable line: ' . trim($line));
            }
            $key = $m[1];
            $rest = rtrim($m[2]);
            if ($rest !== '') {
                if (str_starts_with($rest, '[') || str_starts_with($rest, '{')
                    || str_contains($rest, ' $') || str_starts_with($rest, '&')
                    || str_starts_with($rest, '*')) {
                    // flow syntax / anchors / aliases / tags are out of the
                    // safe subset — strict rejection (never permissive).
                    throw new FrontMatterError('unsupported YAML syntax: ' . trim($line));
                }
                // quoted string?
                if (strlen($rest) >= 2
                    && (($rest[0] === '"' && str_ends_with($rest, '"'))
                     || ($rest[0] === "'" && str_ends_with($rest, "'")))) {
                    $out[$key] = self::unquote($rest);
                } elseif ($rest === '|' || $rest === '>') {
                    // block scalar: gather following more-indented lines
                    [$val, $i] = self::blockScalar($lines, $i + 1);
                    $out[$key] = $val;
                    continue;
                } else {
                    $out[$key] = $rest;
                }
                $i++;
                continue;
            }
            // key with nested block below
            $i++;
            if ($i >= $n) {
                $out[$key] = null;
                continue;
            }
            $first = $lines[$i];
            if (trim($first) === '') {
                $out[$key] = null;
                $i++;
                continue;
            }
            $childIndent = strlen($first) - strlen(ltrim($first));
            if ($childIndent <= 0) {
                $out[$key] = null;
                continue;
            }
            if (str_starts_with(ltrim($first), '- ')) {
                // list (of scalars or maps)
                [$val, $i] = self::parseList($lines, $i, $childIndent);
                $out[$key] = $val;
            } else {
                // nested map block
                [$val, $i] = self::parseMap($lines, $i, $childIndent);
                $out[$key] = $val;
            }
        }
        return $out;
    }

    private static function parseMap(array $lines, int $i, int $indent): array
    {
        $n = count($lines);
        $map = [];
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            $curIndent = strlen($line) - strlen(ltrim($line));
            if ($curIndent < $indent) {
                break;
            }
            if ($curIndent !== $indent) {
                throw new FrontMatterError('inconsistent indentation: ' . trim($line));
            }
            if (str_starts_with(ltrim($line), '- ')) {
                throw new FrontMatterError('unexpected list item inside map: ' . trim($line));
            }
            if (preg_match('/^(\s+)([A-Za-z0-9_]+):\s*(.*)$/', $line, $m) !== 1) {
                throw new FrontMatterError('unparseable nested line: ' . trim($line));
            }
            $key = $m[2];
            $rest = rtrim($m[3]);
            if ($rest !== '') {
                $map[$key] = (strlen($rest) >= 2
                    && in_array($rest[0], ['"', "'"], true)
                    && str_ends_with($rest, $rest[0])) ? self::unquote($rest) : $rest;
                $i++;
                continue;
            }
            // nested value below
            $i++;
            if ($i < $n) {
                $first = $lines[$i];
                if (trim($first) !== '' && str_starts_with(ltrim($first), '- ')
                    && (strlen($first) - strlen(ltrim($first))) > $indent) {
                    [$map[$key], $i] = self::parseList($lines, $i, strlen($first) - strlen(ltrim($first)));
                    continue;
                }
            }
            $map[$key] = null;
        }
        return [$map, $i];
    }

    private static function parseList(array $lines, int $i, int $indent): array
    {
        $n = count($lines);
        $list = [];
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $i++;
                continue;
            }
            $curIndent = strlen($line) - strlen(ltrim($line));
            if ($curIndent < $indent) {
                break;
            }
            if ($curIndent !== $indent || !str_starts_with(ltrim($line), '- ')) {
                throw new FrontMatterError('malformed list entry: ' . trim($line));
            }
            $item = ltrim($line);
            if (preg_match('/^- ([A-Za-z0-9_]+):\s*(.*)$/', $item, $m) === 1) {
                // map item: first key inline, rest on following lines at indent+2
                $map = [];
                $rest = rtrim($m[2]);
                $map[$m[1]] = ($rest !== '' && strlen($rest) >= 2
                    && in_array($rest[0], ['"', "'"], true)
                    && str_ends_with($rest, $rest[0])) ? self::unquote($rest) : ($rest === '' ? null : $rest);
                $i++;
                [$inner, $i] = self::parseMap($lines, $i, $indent + 2);
                $list[] = array_merge($map, $inner);
            } else {
                $val = trim(substr($item, 2));
                if (strlen($val) >= 2 && in_array($val[0], ['"', "'"], true) && str_ends_with($val, $val[0])) {
                    $val = self::unquote($val);
                }
                $list[] = $val;
                $i++;
            }
        }
        return [$list, $i];
    }

    private static function blockScalar(array $lines, int $i): array
    {
        $n = count($lines);
        $buf = [];
        $indent = null;
        while ($i < $n) {
            $line = $lines[$i];
            if (trim($line) === '') {
                $buf[] = '';
                $i++;
                continue;
            }
            $curIndent = strlen($line) - strlen(ltrim($line));
            if ($curIndent <= 0) {
                break;
            }
            $indent ??= $curIndent;
            $buf[] = substr($line, $indent);
            $i++;
        }
        while ($buf !== [] && end($buf) === '') {
            array_pop($buf);
        }
        return [implode("\n", $buf), $i];
    }

    private static function unquote(string $s): string
    {
        $inner = substr($s, 1, -1);
        if ($s[0] === '"') {
            return str_replace(['\\"', '\\n'], ['"', "\n"], $inner);
        }
        return str_replace("''", "'", $inner);
    }
}
