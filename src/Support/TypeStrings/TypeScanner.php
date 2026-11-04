<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

/**
 * The one reader of a TypeScript or PHPDoc type string's nesting and literals: the bracket depth of each byte, the
 * parts between its top-level separators, and where its string and template literals sit.
 *
 * A bracket sits at the depth outside its pair, which floors at zero so malformed input fails toward splitting, and a
 * backslash escapes the next byte of a literal. In TypeScript an interpolation is code one level in, and an arrow owns
 * each `|` and `&` after it on its level until a `,` or `;` there or the level ends; the `>` of `=>` closes nothing.
 *
 * @phpstan-type LiteralSpan = array{int, int}
 * @phpstan-type Scan = array{depths: array<int, int>, spans: list<LiteralSpan>}
 *
 * @internal
 */
final class TypeScanner
{
    private const string OPENERS = '{[(<';

    private const string CLOSERS = '}])>';

    private const string BRACKETS = self::OPENERS.self::CLOSERS;

    private const string QUOTES = '\'"';

    /** Where scan() stops in TypeScript: a literal's opener, a bracket, and each byte the arrow rule reads. */
    private const string TYPESCRIPT_STOPS = self::QUOTES.'`'.self::BRACKETS.'|&,;=';

    private const string PHPDOC_STOPS = self::QUOTES.self::BRACKETS;

    private const string TEMPLATE_STOPS = '\\`$';

    /**
     * The bracket depth of each offset outside literal text, by offset.
     *
     * @return array<int, int>
     */
    public static function depths(string $type, ScanDialect $dialect = ScanDialect::TypeScript): array
    {
        $slow = $dialect === ScanDialect::TypeScript ? self::QUOTES.'`=' : self::QUOTES;

        return strpbrk($type, $slow) === false ? self::bracketDepths($type) : self::scan($type, $dialect)['depths'];
    }

    /**
     * The parts of a type between its depth-0 separators, each trimmed, with empty parts dropped.
     *
     * @param  list<string>  $separators  Single bytes.
     * @return list<string>
     */
    public static function split(string $type, array $separators, ScanDialect $dialect = ScanDialect::TypeScript): array
    {
        $bytes = implode('', $separators);
        $length = strlen($type);
        $parts = [];
        $start = 0;

        if ($bytes !== '' && strpbrk($type, $bytes) !== false) {
            $depths = self::depths($type, $dialect);

            for ($offset = strcspn($type, $bytes); $offset < $length; $offset += 1 + strcspn($type, $bytes, $offset + 1)) {
                if (($depths[$offset] ?? null) === 0) {
                    $parts[] = substr($type, $start, $offset - $start);
                    $start = $offset + 1;
                }
            }
        }

        $parts[] = substr($type, $start);

        return array_values(array_filter(array_map(trim(...), $parts), static fn (string $part): bool => $part !== ''));
    }

    /**
     * The `[start, end)` of each string literal, quotes included, and of each template literal's text segment, its
     * backticks and `${`/`}` included and its interpolations left out, in order.
     *
     * @return list<LiteralSpan>
     */
    public static function literalSpans(string $type, ScanDialect $dialect = ScanDialect::TypeScript): array
    {
        $openers = $dialect === ScanDialect::TypeScript ? self::QUOTES.'`' : self::QUOTES;

        return strpbrk($type, $openers) === false ? [] : self::scan($type, $dialect)['spans'];
    }

    /**
     * The type with each byte of literal text replaced by a space, so every offset still reads the same byte position.
     */
    public static function maskLiterals(string $type, ScanDialect $dialect = ScanDialect::TypeScript): string
    {
        foreach (self::literalSpans($type, $dialect) as [$start, $end]) {
            $type = substr_replace($type, str_repeat(' ', $end - $start), $start, $end - $start);
        }

        return $type;
    }

    /**
     * The depths of a type with no literal and no arrow, where only brackets move the depth.
     *
     * @return array<int, int>
     */
    private static function bracketDepths(string $type): array
    {
        $depths = [];
        $depth = 0;
        $length = strlen($type);

        for ($offset = 0; $offset < $length; $offset++) {
            for ($end = $offset + strcspn($type, self::BRACKETS, $offset); $offset < $end; $offset++) {
                $depths[$offset] = $depth;
            }

            if ($offset === $length) {
                break;
            }

            $depths[$offset] = str_contains(self::OPENERS, $type[$offset]) ? $depth++ : ($depth = max(0, $depth - 1));
        }

        return $depths;
    }

    /**
     * Every depth and literal span of a type, read byte by byte.
     *
     * @return Scan
     */
    private static function scan(string $type, ScanDialect $dialect): array
    {
        $stops = $dialect === ScanDialect::TypeScript ? self::TYPESCRIPT_STOPS : self::PHPDOC_STOPS;
        $length = strlen($type);
        $depths = [];
        $spans = [];
        $depth = 0;
        // A closer never leaves the interpolation it sits in, so `${a)}` still ends at its `}`.
        $floor = 0;
        $templateDepths = [];
        $arrows = [];
        $arrow = -1;

        for ($offset = 0; $offset < $length; $offset++) {
            for ($end = $offset + strcspn($type, $stops, $offset); $offset < $end; $offset++) {
                $depths[$offset] = $depth;
            }

            if ($offset === $length) {
                break;
            }

            $byte = $type[$offset];
            $textAt = null;

            if ($byte === '\'' || $byte === '"') {
                $end = self::stringEnd($type, $offset, $byte);
                $spans[] = [$offset, $end];
                $offset = $end - 1;
            } elseif ($byte === '`') {
                $textAt = $offset + 1;
            } elseif ($byte === '}' && $templateDepths !== [] && $depth === $floor) {
                $arrow = $arrow === $depth ? self::popArrow($arrows) : $arrow;
                $depth = (int) array_pop($templateDepths);
                $floor = $templateDepths === [] ? 0 : $templateDepths[array_key_last($templateDepths)] + 1;
                $textAt = $offset + 1;
            } elseif (str_contains(self::OPENERS, $byte)) {
                $depths[$offset] = $depth++;
            } elseif (str_contains(self::CLOSERS, $byte)) {
                $arrow = $arrow === $depth ? self::popArrow($arrows) : $arrow;
                $depths[$offset] = $depth = max($floor, $depth - 1);
            } elseif ($byte === '|' || $byte === '&') {
                $depths[$offset] = $arrow === $depth ? $depth + 1 : $depth;
            } elseif ($byte === '=') {
                $depths[$offset] = $depth;

                if ($offset + 1 < $length && $type[$offset + 1] === '>') {
                    $depths[++$offset] = $depth;

                    if ($arrow !== $depth) {
                        $arrows[] = $arrow = $depth;
                    }
                }
            } else {
                // A `,` or `;` ends the arrow's return type on its level.
                $arrow = $arrow === $depth ? self::popArrow($arrows) : $arrow;
                $depths[$offset] = $depth;
            }

            if ($textAt !== null) {
                [$end, $interpolates] = self::templateText($type, $textAt);
                $spans[] = [$offset, $end];
                $offset = $end - 1;

                if ($interpolates) {
                    $templateDepths[] = $depth;
                    $floor = ++$depth;
                }
            }
        }

        return ['depths' => $depths, 'spans' => $spans];
    }

    /**
     * Drop the innermost arrow, returning the depth of the arrow now innermost, or -1 for none.
     *
     * @param  list<int>  $arrows
     */
    private static function popArrow(array &$arrows): int
    {
        array_pop($arrows);

        return $arrows === [] ? -1 : $arrows[array_key_last($arrows)];
    }

    /**
     * The offset just past a quoted string's closing quote, or the type's length when it never closes.
     */
    private static function stringEnd(string $type, int $open, string $quote): int
    {
        $length = strlen($type);
        $stops = '\\'.$quote;

        for ($offset = $open + 1; $offset < $length; $offset += 2) {
            $offset += strcspn($type, $stops, $offset);

            if ($offset < $length && $type[$offset] === $quote) {
                return $offset + 1;
            }
        }

        return $length;
    }

    /**
     * Where one template text segment ends, just past its closing backtick or its `${`, and whether a `${` ends it.
     *
     * @return array{int, bool}
     */
    private static function templateText(string $type, int $offset): array
    {
        $length = strlen($type);

        while ($offset < $length) {
            $offset += strcspn($type, self::TEMPLATE_STOPS, $offset);

            if ($offset >= $length) {
                break;
            }

            if ($type[$offset] === '`') {
                return [$offset + 1, false];
            }

            if ($type[$offset] === '$') {
                if ($offset + 1 < $length && $type[$offset + 1] === '{') {
                    return [$offset + 2, true];
                }

                $offset++;
            } else {
                $offset += 2;
            }
        }

        return [$length, false];
    }
}
