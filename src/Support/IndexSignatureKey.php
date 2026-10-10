<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support;

/**
 * The grammar of a generated index-signature key, in both directions: `[key: number]`, `[key: string]`, and the
 * template literal ``[key: `${string}_tag`]`` built from literal text around dynamic parts.
 *
 * Template text is written the way TypeScript reads it: a backslash doubled, `${` as `\${`, and a carriage return as
 * `\r`. Written raw, a backslash would fail to compile (TS1125, TS1337) or match other text, and a CR would read as LF.
 *
 * @internal
 */
final class IndexSignatureKey
{
    /** A generated signature key, unanchored, for a pattern that finds one inside a type. */
    public const string PATTERN = '\[[a-zA-Z_$][a-zA-Z0-9_$]*: (?:string|number|`[^`]*`)\]';

    /**
     * The template-literal signature for these parts, or null without both a literal and a dynamic part.
     *
     * A literal part holding a backtick declines, since is() has no escape for one.
     *
     * @param  list<string|null>  $parts  each literal part's text, or null for a dynamic one
     */
    public static function fromParts(array $parts): ?string
    {
        $pattern = '';
        $hasLiteral = false;
        $hasDynamic = false;

        foreach ($parts as $part) {
            if ($part === null) {
                $pattern .= '${string}';
                $hasDynamic = true;

                continue;
            }

            if (str_contains($part, '`')) {
                return null;
            }

            $pattern .= strtr($part, ['\\' => '\\\\', '${' => '\\${', "\r" => '\\r']);
            $hasLiteral = true;
        }

        return $hasLiteral && $hasDynamic ? '[key: `'.$pattern.'`]' : null;
    }

    /**
     * Whether a key is a generated index signature rather than a property name: it prints unquoted in a type
     * position and can never carry `?:`.
     */
    public static function is(string $key): bool
    {
        return preg_match('/^'.self::PATTERN.'$/', $key) === 1;
    }

    /**
     * A template-literal signature's literal text between its `${string}` placeholders, or null for any other key.
     *
     * @return non-empty-list<string>|null
     */
    public static function literalSegments(string $name): ?array
    {
        if (! self::is($name) || preg_match('/`(.*)`\]$/s', $name, $template) !== 1) {
            return null;
        }

        // A `${string}` that no escape consumes is a placeholder.
        $segments = preg_split('/\\\\.(*SKIP)(*FAIL)|\$\{string\}/s', $template[1]);

        if ($segments === false || $segments === []) {
            return null; // @codeCoverageIgnore
        }

        return array_map(
            fn (string $segment): string => (string) preg_replace_callback(
                '/\\\\(.)/s',
                fn (array $escape): string => $escape[1] === 'r' ? "\r" : $escape[1],
                $segment,
            ),
            $segments,
        );
    }

    /**
     * The spellings of a signature's name, other than the name, that a #[TsCasts] key may use for it: each `\\` read
     * as `\` (a single-quoted paste), each `\r` read as a raw CR (a double-quoted paste), or both.
     *
     * @return list<string>
     */
    public static function castSpellings(string $name): array
    {
        $spellings = [
            self::readEscapes($name, ['\\' => '\\']),
            self::readEscapes($name, ['r' => "\r"]),
            self::readEscapes($name, ['\\' => '\\', 'r' => "\r"]),
        ];

        return array_values(array_diff(array_unique($spellings), [$name]));
    }

    /**
     * The text with each escape the map names read back, pair by pair from the left, and every other one kept.
     *
     * @param  array<string, string>  $as  escaped character => what it reads as
     */
    private static function readEscapes(string $text, array $as): string
    {
        return (string) preg_replace_callback(
            '/\\\\(.)/s',
            fn (array $escape): string => $as[$escape[1]] ?? $escape[0],
            $text,
        );
    }
}
