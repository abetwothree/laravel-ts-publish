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

    /** The options PCRE reads only at the very start of a pattern, such as `(*UTF8)`, and no backtracking verb. */
    private const string LEADING_VERBS = '/^(?:\(\*(?!(?:F|FAIL|ACCEPT|COMMIT|PRUNE|SKIP|THEN)\))[A-Z_][A-Z0-9_]*(?:=\d+)?\))*/';

    /** A verb whose name PHP sends under `MARK` when the match passes it; `(*SKIP:name)` only looks a mark up. */
    private const string MARK_VERB = '/\(\*(?:MARK|PRUNE|THEN|COMMIT|ACCEPT)?:/';

    /** The delimiters the probe pattern may take, the first the pattern does not hold. */
    private const array PROBE_DELIMITERS = ["\x01", "\x02", "\x03", "\x04", '~', '#', '%', '@', '!', ';'];

    /** @var array<string, list<int|string>|null> */
    private static array $groups = [];

    /**
     * The type of the matches a call writes for a pattern and its flags, or null for flags the function rejects, a
     * pattern PCRE does not compile, or marks in pattern order, which PHP keys by the matches that passed one.
     */
    public static function type(string $pattern, int $flags, bool $all): ?string
    {
        $groups = self::groups($pattern);
        $marks = preg_match(self::MARK_VERB, $pattern) === 1;
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
            ! $all => self::matched($groups, $whole, $group, ! $nullable, $marks)
                .(self::named($groups) || $marks ? ' | never[]' : ''),
            ($flags & PREG_SET_ORDER) !== 0 => TsTypeShape::listType([self::matched($groups, $whole, $group, ! $nullable, $marks)]),
            $marks => null,
            // Pattern order keys every group, a match or none, each the list of what it took in each match.
            default => self::matched($groups, TsTypeShape::listType([$whole]), TsTypeShape::listType([$group]), false, false),
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
     * The pattern wrapped in a DEFINE group, which matches the empty string without entering it, where PCRE fails some
     * leading `(*F)|` in a group repeated zero times; null when the pattern has no delimiters or holds every probe one.
     *
     * `\E` ends a `\Q` left open, and `\r\n\0` ends an `x`-flag comment under each newline convention a verb can set.
     */
    private static function probe(string $pattern): ?string
    {
        $start = strspn($pattern, " \t\n\r\v\f");
        $open = $pattern[$start] ?? '';
        $end = $open === '' || ctype_alnum($open) || $open === '\\' ? false : strrpos($pattern, self::CLOSING[$open] ?? $open);
        $delimiter = array_find(self::PROBE_DELIMITERS, static fn (string $candidate): bool => ! str_contains($pattern, $candidate));

        if ($end === false || $end <= $start || $delimiter === null) {
            return null;
        }

        $body = substr($pattern, $start + 1, $end - $start - 1);
        $verbs = preg_match(self::LEADING_VERBS, $body, $leading) === 1 ? $leading[0] : '';

        return $delimiter.$verbs.'(?(DEFINE)(?:'.substr($body, strlen($verbs))."\\E\r\n\0))".$delimiter.substr($pattern, $end + 1);
    }

    /**
     * One match's groups: a list while no group is named and no verb marks it, else an object keyed as PHP keys it,
     * each group optional when PHP drops the trailing groups that did not take part, and the mark when one may.
     *
     * @param  list<int|string>  $groups
     */
    private static function matched(array $groups, string $whole, string $group, bool $optional, bool $marks): string
    {
        if (! self::named($groups) && ! $marks) {
            return TsTypeShape::listType([$groups === [] ? $whole : $group]);
        }

        $members = ['"0": '.$whole];

        foreach ($groups as $key) {
            $members[] = JsEmitter::validJsObjectKey((string) $key).($optional ? '?: ' : ': ').$group;
        }

        return '{ '.implode('; ', [...$members, ...($marks ? ['MARK?: string'] : [])]).' }';
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
