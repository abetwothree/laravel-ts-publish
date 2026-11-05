<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Calls;

use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * The matches preg_match() and preg_match_all() write for a literal pattern and constant flags, its groups read by
 * PCRE itself, so escapes, classes, `\Q…\E`, branch resets, comments and the `x` and `n` flags count as PHP's do.
 *
 * @internal
 */
final class PregMatches
{
    /** The closing delimiter of each bracket-style opening one; any other delimiter closes itself. */
    private const array CLOSING = ['(' => ')', '[' => ']', '{' => '}', '<' => '>'];

    /** The verbs PCRE reads only at the very start of a pattern, such as `(*UTF8)`. */
    private const string LEADING_VERBS = '/^(?:\(\*[A-Z_][A-Z0-9_]*(?:=\d+)?\))*/';

    /** The delimiter the probe pattern takes, which no pattern this reads holds. */
    private const string PROBE_DELIMITER = "\x01";

    /** @var array<string, list<int|string>|null> */
    private static array $groups = [];

    /**
     * The type of the matches a call writes for a pattern and its flags, or null for flags the function rejects or a
     * pattern PCRE does not compile.
     */
    public static function type(string $pattern, int $flags, bool $all): ?string
    {
        $groups = self::groups($pattern);
        $valid = $all
            ? ($flags & ~(PREG_PATTERN_ORDER | PREG_SET_ORDER | PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) === 0
                && ($flags & (PREG_PATTERN_ORDER | PREG_SET_ORDER)) !== (PREG_PATTERN_ORDER | PREG_SET_ORDER)
            : ($flags & ~(PREG_OFFSET_CAPTURE | PREG_UNMATCHED_AS_NULL)) === 0;

        if ($groups === null || ! $valid) {
            return null;
        }

        $nullable = ($flags & PREG_UNMATCHED_AS_NULL) !== 0;
        $whole = ($flags & PREG_OFFSET_CAPTURE) !== 0 ? '[string, number]' : 'string';
        $group = ($flags & PREG_OFFSET_CAPTURE) !== 0
            ? '['.($nullable ? 'string | null' : 'string').', number]'
            : ($nullable ? 'string | null' : 'string');

        return match (true) {
            ! $all => self::matched($groups, $whole, $group, ! $nullable).(self::named($groups) ? ' | never[]' : ''),
            ($flags & PREG_SET_ORDER) !== 0 => TsTypeShape::listType([self::matched($groups, $whole, $group, ! $nullable)]),
            // Pattern order keys every group, a match or none, each the list of what it took in each match.
            default => self::matched($groups, TsTypeShape::listType([$whole]), TsTypeShape::listType([$group]), false),
        };
    }

    /**
     * The groups a pattern's matches key past the whole match, in PHP's order, a named group's name before its number;
     * null when PCRE does not compile the pattern.
     *
     * PCRE keeps every group of a pattern it never enters as null, so a probe that skips the pattern names them all.
     *
     * @return list<int|string>|null
     */
    private static function groups(string $pattern): ?array
    {
        if (array_key_exists($pattern, self::$groups)) {
            return self::$groups[$pattern];
        }

        $probe = self::probe($pattern);
        $groups = null;

        if ($probe !== null && @preg_match($pattern, '') !== false && @preg_match($probe, '', $matches, PREG_UNMATCHED_AS_NULL) === 1) {
            $groups = array_slice(array_keys($matches), 1);
        }

        return self::$groups[$pattern] = $groups;
    }

    /**
     * The pattern wrapped in a group repeated zero times, which matches the empty string without entering it; null when
     * the pattern has no delimiters or holds the probe's own.
     *
     * `\E` ends a `\Q` left open and a newline ends an `x`-flag comment, so neither swallows the closing parenthesis.
     */
    private static function probe(string $pattern): ?string
    {
        $start = strspn($pattern, " \t\n\r\v\f");
        $open = $pattern[$start] ?? '';
        $end = $open === '' || ctype_alnum($open) || $open === '\\' ? false : strrpos($pattern, self::CLOSING[$open] ?? $open);

        if ($end === false || $end <= $start || str_contains($pattern, self::PROBE_DELIMITER)) {
            return null;
        }

        $body = substr($pattern, $start + 1, $end - $start - 1);
        $verbs = preg_match(self::LEADING_VERBS, $body, $leading) === 1 ? $leading[0] : '';

        return self::PROBE_DELIMITER.$verbs.'(?:'.substr($body, strlen($verbs))."\\E\n){0}"
            .self::PROBE_DELIMITER.substr($pattern, $end + 1);
    }

    /**
     * One match's groups: a list while no group is named, else an object keyed as PHP keys it, each group optional
     * when PHP drops the trailing groups that did not take part.
     *
     * @param  list<int|string>  $groups
     */
    private static function matched(array $groups, string $whole, string $group, bool $optional): string
    {
        if (! self::named($groups)) {
            return TsTypeShape::listType([$groups === [] ? $whole : $group]);
        }

        $members = ['"0": '.$whole];

        foreach ($groups as $key) {
            $members[] = JsEmitter::validJsObjectKey((string) $key).($optional ? '?: ' : ': ').$group;
        }

        return '{ '.implode('; ', $members).' }';
    }

    /**
     * Whether a pattern names any of its groups, which keys the matches as an object.
     *
     * @param  list<int|string>  $groups
     */
    private static function named(array $groups): bool
    {
        return array_any($groups, static fn (int|string $key): bool => is_string($key));
    }
}
