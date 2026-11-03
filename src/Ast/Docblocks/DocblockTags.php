<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Docblocks;

use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;

/**
 * Reads a docblock's tags by family, each tag captured whole from the start of its line, in PHPStan's order:
 * `@phpstan-X`, then `@psalm-X`, then `@X`. A vague tag yields to a later one of its family that is not.
 *
 * @phpstan-type Capture = array{string, string}
 * @phpstan-type Family = key-of<self::FAMILIES>
 *
 * @internal
 */
final class DocblockTags
{
    private const array FAMILIES = [
        'return' => ['@phpstan-return', '@psalm-return', '@return'],
        'var' => ['@phpstan-var', '@psalm-var', '@var'],
        'param' => ['@phpstan-param', '@psalm-param', '@param'],
        'property' => ['@phpstan-property', '@psalm-property', '@property', '@property-read'],
        'extends' => ['@phpstan-extends', '@template-extends', '@extends'],
        'mixin' => ['@phpstan-mixin', '@mixin'],
        // `@psalm-use` is no PHPStan tag, but dropping it would unbind the templates a docblock binds through it.
        'use' => ['@phpstan-use', '@psalm-use', '@template-use', '@use'],
    ];

    /** Some vendor traits drop the `$` from `@property`, so such a name is read when no description follows it. */
    private const array BARE_NAME_FAMILIES = ['property'];

    private const array VAGUE_KEYWORDS = [
        'mixed', 'mixed[]', 'array', 'list', 'iterable', 'object', 'non-empty-array', 'non-empty-list',
    ];

    private const string NAME = '[a-zA-Z_\x80-\xff][\w\x80-\xff]*';

    /**
     * The type the family's first tag spells, or a later tag's where the first reads vague and that one does not.
     *
     * @param  Family  $family
     */
    public static function type(string $docComment, string $family): ?string
    {
        $content = self::content($docComment);
        $types = [];

        foreach (self::FAMILIES[$family] as $tag) {
            $type = self::captures($content, $tag)[0][0] ?? null;

            if ($type !== null) {
                $types[] = $type;
            }
        }

        return $types === [] ? null : self::preferPrecise($types);
    }

    /**
     * The type each named `param`, `property` or `var` tag gives its name, keyed in family order, then first seen.
     *
     * @param  Family  $family
     * @return array<string, string>
     */
    public static function named(string $docComment, string $family): array
    {
        $content = self::content($docComment);
        $bare = in_array($family, self::BARE_NAME_FAMILIES, true);
        $byName = [];

        foreach (self::FAMILIES[$family] as $tag) {
            foreach (self::captures($content, $tag) as [$type, $rest]) {
                $name = self::name($rest, $bare);

                if ($name !== null) {
                    $byName[$name][] = $type;
                }
            }
        }

        return array_map(self::preferPrecise(...), $byName);
    }

    /**
     * Every type the family's tags spell, prefixed tags first.
     *
     * @param  Family  $family
     * @return list<string>
     */
    public static function all(string $docComment, string $family): array
    {
        $content = self::content($docComment);
        $types = [];

        foreach (self::FAMILIES[$family] as $tag) {
            foreach (self::captures($content, $tag) as [$type]) {
                $types[] = $type;
            }
        }

        return $types;
    }

    /**
     * Each name a `@template`, `@phpstan-template` or `@psalm-template` tag declares, once, in first-seen order.
     *
     * @return list<string>
     */
    public static function templates(string $docComment): array
    {
        preg_match_all(
            '/^[ \t]*@(?:phpstan-|psalm-)?template(?:-covariant|-contravariant)?\s+(\w+)/m',
            self::content($docComment),
            $matches,
        );

        return array_values(array_unique($matches[1]));
    }

    /**
     * Whether a PHPDoc type names no element type: every arm but `null` is `mixed`, `mixed[]`, a bare `array`, `list`,
     * `iterable`, `object`, `non-empty-array` or `non-empty-list`, or a generic whose last argument is `mixed`.
     */
    public static function isVague(string $phpDoc): bool
    {
        $arms = array_filter(
            array_map(trim(...), LaravelTsPublish::splitPhpDocUnionType(ltrim(trim($phpDoc), '?'))),
            fn (string $arm): bool => strtolower($arm) !== 'null',
        );

        return $arms !== [] && array_all($arms, self::isVagueArm(...));
    }

    /**
     * The docblock's text without its comment markers.
     */
    private static function content(string $docComment): string
    {
        return trim((string) preg_replace(['#^[ \t]*/?\*+/?#m', '#\*+/\s*$#'], '', $docComment));
    }

    /**
     * Each non-empty type a line-leading tag spells, with the text that follows it.
     *
     * @return list<Capture>
     */
    private static function captures(string $content, string $tag): array
    {
        if (! str_contains($content, $tag)) {
            return [];
        }

        preg_match_all('/^[ \t]*'.preg_quote($tag, '/').'\s+/m', $content, $matches, PREG_OFFSET_CAPTURE);

        $captures = [];

        foreach ($matches[0] as [$match, $offset]) {
            $capture = self::capture(substr($content, $offset + strlen($match)));

            if ($capture[0] !== '') {
                $captures[] = $capture;
            }
        }

        return $captures;
    }

    /**
     * The type expression a text starts with, ending at the separator that ends it, and the text that follows it.
     *
     * @return Capture
     */
    private static function capture(string $text): array
    {
        $rest = ltrim($text);
        $type = '';
        $depth = 0;
        $length = strlen($rest);
        $end = $length;

        for ($i = 0; $i < $length; $i++) {
            $char = $rest[$i];
            $quoteEnd = $char === "'" || $char === '"' ? self::literalEnd($rest, $i) : null;

            if ($quoteEnd !== null) {
                $type .= substr($rest, $i, $quoteEnd - $i + 1);
                $i = $quoteEnd;

                continue;
            }

            $isSpace = ctype_space($char);

            if ($isSpace && $depth === 0 && ! self::spaceContinuesType($type, substr($rest, $i + 1))) {
                $end = $i;

                break;
            }

            $depth += (int) in_array($char, ['{', '<', '('], true) - (int) in_array($char, ['}', '>', ')'], true);
            $type .= $isSpace ? ' ' : $char;

            // An unmatched closer drives depth below zero, where the depth-zero space test
            // can never fire again — without this the walk swallows the `$name` and the prose.
            if ($depth < 0) {
                $end = $i + 1;

                break;
            }
        }

        return [trim($type), substr($rest, $end)];
    }

    /**
     * The offset of the quote that closes the literal opened at an offset, a backslash escaping the byte after it; null
     * when none closes it on its line, so a lone apostrophe stays an ordinary character.
     */
    private static function literalEnd(string $text, int $open): ?int
    {
        $quote = $text[$open];
        $length = strlen($text);

        for ($i = $open + 1; $i < $length && $text[$i] !== "\n"; $i++) {
            if ($text[$i] === '\\') {
                $i++;
            } elseif ($text[$i] === $quote) {
                return $i;
            }
        }

        return null;
    }

    /**
     * Whether a depth-zero space is inside the type rather than after it.
     */
    private static function spaceContinuesType(string $captured, string $remaining): bool
    {
        $captured = rtrim($captured);
        $remaining = ltrim($remaining);

        // A `&` before a parameter name marks a by-reference `@param`, not an intersection. A `:` after a `)` opens a
        // callable's return type. A `<` or `{` after the space, as in `array <string, int>`, continues a generic or a
        // shape, but a `(` does not: `@return int (the count)` is prose.
        return str_ends_with($captured, '|') || str_ends_with($captured, '&')
            || str_starts_with($remaining, '|')
            || preg_match('/^[<{]/', $remaining) === 1
            || (str_starts_with($remaining, '&') && preg_match('/^&\s*(?:\.\.\.)?\$/', $remaining) !== 1)
            || preg_match('/\)\s*:$/', $captured) === 1
            || (str_ends_with($captured, ')') && str_starts_with($remaining, ':'));
    }

    /**
     * The name a tag's text names after its type: a `$name`, by reference or variadic, else a bare name its line ends
     * on where the family allows one.
     */
    private static function name(string $rest, bool $bare): ?string
    {
        if (preg_match('/^\s*&?\s*(?:\.\.\.)?\$('.self::NAME.')/', $rest, $match) === 1) {
            return $match[1];
        }

        return $bare && preg_match('/^[ \t]+('.self::NAME.')[ \t]*(?:\r?\n|$)/', $rest, $match) === 1 ? $match[1] : null;
    }

    /**
     * Whether one union arm is a vague keyword or a generic, of any base name, whose last argument is `mixed`.
     */
    private static function isVagueArm(string $arm): bool
    {
        return in_array(strtolower($arm), self::VAGUE_KEYWORDS, true)
            || preg_match('/^[\\\\\w-]+\s*<(?:.*,)?\s*mixed\s*>$/is', $arm) === 1;
    }

    /**
     * The first type, unless it is vague and a later one is not.
     *
     * @param  non-empty-list<string>  $types
     */
    private static function preferPrecise(array $types): string
    {
        if (count($types) > 1) {
            foreach ($types as $type) {
                if (! self::isVague($type)) {
                    return $type;
                }
            }
        }

        return $types[0];
    }
}
