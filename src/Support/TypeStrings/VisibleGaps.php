<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;

/**
 * The spelling of a visible gap: under a refined `@return`, a union arm no rule could type stays in the type text as
 * `$gapN`, and an object that may hold keys no rule could name carries the open marker `#$open: unknown`, so the
 * declared type can fill both in place. Both are stripped before a type is published.
 *
 * A gap is read only as a whole union arm, so a PHP key or a string literal spelled `$gap1` stays, and `#$open` is no
 * index signature and no key a printer writes. Each reader returns its input as it is when holds() is false.
 *
 * @phpstan-type SetAside = array{0: string, 1: list<string>}
 * @phpstan-type Member = array{string, string}
 * @phpstan-type MarkedMembers = array{0: list<Member>, 1: bool}
 * @phpstan-type Stripped = array{0: string, 1: list<int>}
 *
 * @internal
 */
final class VisibleGaps
{
    public const string MARKER_KEY = '#$open';

    public const string MARKER = self::MARKER_KEY.': unknown';

    private const string GAP_PREFIX = '$gap';

    /**
     * Whether one union arm is a gap.
     */
    public static function isGap(string $arm): bool
    {
        return preg_match('/^\$gap[1-9]\d*$/D', $arm) === 1;
    }

    /**
     * Whether one printed object key is the open marker's.
     */
    public static function isMarker(string $key): bool
    {
        return $key === self::MARKER_KEY;
    }

    /**
     * Whether a type may spell a gap or the open marker; false proves it spells neither.
     */
    public static function holds(string $type): bool
    {
        return str_contains($type, self::GAP_PREFIX) || str_contains($type, self::MARKER_KEY);
    }

    /**
     * The gap arm for a gap id.
     */
    public static function arm(int $id): string
    {
        return self::GAP_PREFIX.$id;
    }

    /**
     * The type with a gap arm appended, as restored() appends it.
     */
    public static function withArm(string $type, int $id): string
    {
        return self::restored($type, [self::arm($id)]);
    }

    /**
     * The id of each gap arm at any depth, in order, each once.
     *
     * @return list<int>
     */
    public static function ids(string $type): array
    {
        return self::stripped($type)[1];
    }

    /**
     * The type without its own top-level gap arms, its other arms joined as printed or `unknown` when none is left,
     * and those gap arms.
     *
     * @return SetAside
     */
    public static function aside(string $type): array
    {
        if (! self::holds($type)) {
            return [$type, []];
        }

        $arms = TsTypeShape::splitTopLevel($type, ['|']);
        $gaps = array_values(array_unique(array_filter($arms, self::isGap(...))));

        if ($gaps === []) {
            return [$type, []];
        }

        $rest = array_diff($arms, $gaps);

        return [$rest === [] ? 'unknown' : implode(' | ', $rest), $gaps];
    }

    /**
     * The type with each of these gap arms it lacks at the top level appended, its arms grouped as they join;
     * `unknown` already stands for whatever a gap stands for, so it takes none.
     *
     * @param  list<string>  $gaps
     */
    public static function restored(string $type, array $gaps): string
    {
        $arms = TsTypeShape::splitTopLevel($type, ['|']);
        $missing = array_diff(array_unique($gaps), $arms);

        if ($missing === [] || $type === 'unknown') {
            return $type;
        }

        $grouped = array_map(TsTypeShape::unionArm(...), $arms);
        $head = $grouped === $arms ? [$type] : $grouped;

        return implode(' | ', [...($arms === [] ? [] : $head), ...$missing]);
    }

    /**
     * What a read makes of the type with its top-level gaps set aside, those gaps restored onto it; a read that
     * declines stays null.
     *
     * @param  Closure(string): ?string  $read
     */
    public static function carried(string $type, Closure $read): ?string
    {
        [$rest, $gaps] = self::aside($type);
        $result = $read($rest);

        return $result === null ? null : self::restored($result, $gaps);
    }

    /**
     * One object literal's members without the open marker, and whether it had the marker; null for any other type.
     *
     * @return MarkedMembers|null
     */
    public static function members(string $object): ?array
    {
        $members = TsTypeShape::members($object);

        if ($members === null || ! self::holds($object)) {
            return $members === null ? null : [$members, false];
        }

        $named = array_values(array_filter($members, static fn (array $member): bool => ! self::isMarker($member[0])));

        return [$named, count($named) !== count($members)];
    }

    /**
     * One object literal with the open marker as its last member, the rest of its text as written; any other type as
     * it is.
     */
    public static function withMarker(string $object): string
    {
        $members = TsTypeShape::members($object);

        if ($members === null || in_array(self::MARKER_KEY, array_column($members, 0), true)) {
            return $object;
        }

        if ($members === []) {
            return '{ '.self::MARKER.' }';
        }

        // A member ends with its value, never with a separator or a space, so the last one ends where those stop.
        $end = strlen(rtrim(substr($object, 0, -1), " \t\n\r\0\x0B;,"));

        return substr($object, 0, $end).TsTypeShape::memberSeparator($object).self::MARKER.substr($object, $end);
    }

    /**
     * A rebuilt object literal with the open marker where it is open.
     */
    public static function opened(string $object, bool $open): string
    {
        return $open ? self::withMarker($object) : $object;
    }

    /**
     * The type with every gap arm and open marker at any depth removed, exactly as the reader prints it with no gap
     * shown, and the id of each gap removed, in order, each once.
     *
     * @return Stripped
     */
    public static function stripped(string $type): array
    {
        if (! self::holds($type)) {
            return [$type, []];
        }

        $ids = [];
        $text = TypeRewriter::rewrite(
            $type,
            static function (string $arm) use (&$ids): ?string {
                if (! self::isGap($arm)) {
                    return $arm;
                }

                $ids[] = (int) substr($arm, strlen(self::GAP_PREFIX));

                return null;
            },
            static fn (string $key, string $value): ?array => self::isMarker($key) ? null : [$key, $value],
        );

        return [$text, array_values(array_unique($ids))];
    }
}
