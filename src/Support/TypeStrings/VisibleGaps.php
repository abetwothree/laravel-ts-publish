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
 * Under an outer declaration, an arm only a declaration produced is a mark: `$declNull` for a `null`, which a reader
 * reads as that `null` and a carried read keeps, and `$declKeys` beside the `Record` an untyped array's keys hedge,
 * carried as a gap is. Stripping turns them back into the `null` they stand for and drops the hedge.
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

    /** The mark of a `null` only a declaration produced, such as a nullable column's or a `?T` return's. */
    public const string DECLARED_NULL = '$declNull';

    /** The mark beside the `Record` arm a map over an untyped array hedges its keys with, which PHP's `array` declares. */
    public const string DECLARED_KEYS = '$declKeys';

    private const string GAP_PREFIX = '$gap';

    private const string MARK_PREFIX = '$decl';

    /**
     * Whether one union arm is a gap or a mark.
     */
    public static function isGap(string $arm): bool
    {
        return self::isMark($arm) || preg_match('/^\$gap[1-9]\d*$/D', $arm) === 1;
    }

    /**
     * Whether one union arm marks an arm only a declaration produced.
     */
    public static function isMark(string $arm): bool
    {
        return $arm === self::DECLARED_NULL || $arm === self::DECLARED_KEYS;
    }

    /**
     * Whether one printed object key is the open marker's.
     */
    public static function isMarker(string $key): bool
    {
        return $key === self::MARKER_KEY;
    }

    /**
     * Whether a type may spell a gap, a mark or the open marker; false proves it spells none.
     */
    public static function holds(string $type): bool
    {
        // Every gap, mark and marker spells a `$`, which few types hold, so one scan clears most of them.
        return str_contains($type, '$') && (self::holdsUntyped($type) || self::holdsMarks($type));
    }

    /**
     * Whether a type may spell a gap or the open marker, an arm or a key no rule typed; false proves it spells neither.
     */
    public static function holdsUntyped(string $type): bool
    {
        return str_contains($type, self::GAP_PREFIX) || str_contains($type, self::MARKER_KEY);
    }

    /**
     * Whether a type may spell a mark; false proves it spells none.
     */
    public static function holdsMarks(string $type): bool
    {
        return str_contains($type, self::MARK_PREFIX);
    }

    /**
     * The type with its top-level `null` arm spelled as the declared-null mark, once, where another arm stands beside
     * it.
     */
    public static function nullMarked(string $type): string
    {
        $arms = TsTypeShape::splitTopLevel($type, ['|']);

        if (! in_array('null', $arms, true) || array_diff($arms, ['null', self::DECLARED_NULL]) === []) {
            return $type;
        }

        $marked = [];

        foreach ($arms as $arm) {
            if ($arm !== 'null' && $arm !== self::DECLARED_NULL) {
                $marked[] = $arm;
            } elseif (! in_array(self::DECLARED_NULL, $marked, true)) {
                $marked[] = self::DECLARED_NULL;
            }
        }

        return implode(' | ', $marked);
    }

    /**
     * The type with each mark at any depth turned back into what it stands for: `$declNull` into `null`, and the key
     * hedge's mark dropped.
     */
    public static function unmarked(string $type): string
    {
        return self::holdsMarks($type) ? TypeRewriter::rewrite($type, self::unmarkedArm(...)) : $type;
    }

    /**
     * The type with each declared null's mark at any depth turned back into the `null` it stands for, the key hedge's
     * mark kept.
     */
    public static function nullUnmarked(string $type): string
    {
        return str_contains($type, self::DECLARED_NULL)
            ? TypeRewriter::rewrite($type, static fn (string $arm): string => $arm === self::DECLARED_NULL ? 'null' : $arm)
            : $type;
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
        return str_contains($type, self::GAP_PREFIX) ? self::stripped($type)[1] : [];
    }

    /**
     * The type without its own top-level gap arms, its other arms joined as printed or `unknown` when none is left,
     * and those gap arms. A declared null's mark is read as the `null` it stands for, unless $marks sets it aside too,
     * as the merge with a declaration does (DeclaredTypeMerge).
     *
     * @return SetAside
     */
    public static function aside(string $type, bool $marks = false): array
    {
        if (! self::holds($type)) {
            return [$type, []];
        }

        $arms = TsTypeShape::splitTopLevel($type, ['|']);
        $gaps = array_values(array_unique(array_filter(
            $arms,
            static fn (string $arm): bool => self::isGap($arm) && ($marks || $arm !== self::DECLARED_NULL),
        )));
        $readsNull = ! $marks && in_array(self::DECLARED_NULL, $arms, true);

        if ($gaps === [] && ! $readsNull) {
            return [$type, []];
        }

        $rest = array_diff($arms, $gaps);

        // Two arms spelled alike may name two classes, so only the `null` the mark stands for joins one already there.
        if ($readsNull) {
            $rest = in_array('null', $rest, true)
                ? array_diff($rest, [self::DECLARED_NULL])
                : array_map(static fn (string $arm): string => $arm === self::DECLARED_NULL ? 'null' : $arm, $rest);
        }

        return [match (count($rest)) {
            0 => 'unknown',
            1 => TsTypeShape::loneArm(reset($rest)),
            default => implode(' | ', $rest),
        }, $gaps];
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
     * What a read makes of the type with its top-level gaps set aside, those gaps restored onto it and a declared
     * null's mark onto the `null` the read keeps (remarked()); a read that declines stays null.
     *
     * @param  Closure(string): ?string  $read
     */
    public static function carried(string $type, Closure $read): ?string
    {
        [$rest, $gaps] = self::aside($type);
        $result = $read($rest);

        return $result === null ? null : self::restored(self::remarked($type, $result), $gaps);
    }

    /**
     * A read's result with its top-level `null` marked again where the type it read held a `null` only as a declared
     * one's mark, which aside() read as that `null`.
     */
    public static function remarked(string $read, string $result): string
    {
        if (! self::holdsMarks($read)) {
            return $result;
        }

        $arms = TsTypeShape::splitTopLevel($read, ['|']);

        return in_array(self::DECLARED_NULL, $arms, true) && ! in_array('null', $arms, true) ? self::nullMarked($result) : $result;
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
     * The type with every gap arm and open marker at any depth removed and every mark unmarked(), exactly as the reader
     * prints it with no gap shown, or each mark kept where $keepsMarks holds, and the id of each gap removed, in order,
     * each once.
     *
     * @return Stripped
     */
    public static function stripped(string $type, bool $keepsMarks = false): array
    {
        if (! self::holds($type)) {
            return [$type, []];
        }

        $ids = [];
        $text = TypeRewriter::rewrite(
            $type,
            static function (string $arm) use (&$ids, $keepsMarks): ?string {
                if (self::isMark($arm) || ! self::isGap($arm)) {
                    return $keepsMarks ? $arm : self::unmarkedArm($arm);
                }

                $ids[] = (int) substr($arm, strlen(self::GAP_PREFIX));

                return null;
            },
            static fn (string $key, string $value): ?array => self::isMarker($key) ? null : [$key, $value],
        );

        return [$text, array_values(array_unique($ids))];
    }

    /**
     * The type with every gap arm and open marker at any depth removed, as stripped() removes them, each mark kept: as
     * a read out of the gap mode spells it.
     */
    public static function withoutGaps(string $type): string
    {
        return self::holdsUntyped($type) ? TypeRewriter::rewrite(
            $type,
            static fn (string $arm): ?string => self::isGap($arm) && ! self::isMark($arm) ? null : $arm,
            static fn (string $key, string $value): ?array => self::isMarker($key) ? null : [$key, $value],
        ) : $type;
    }

    /**
     * One union arm as unmarked() leaves it: a declared-null mark as `null`, the key hedge's mark dropped.
     */
    private static function unmarkedArm(string $arm): ?string
    {
        return match ($arm) {
            self::DECLARED_NULL => 'null',
            self::DECLARED_KEYS => null,
            default => $arm,
        };
    }
}
