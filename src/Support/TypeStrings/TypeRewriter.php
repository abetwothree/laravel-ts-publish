<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;

/**
 * Rewrite a TypeScript type string arm by arm and member by member, at every depth: through unions, intersections,
 * parentheses, `T[]`, tuples, object members, `keyof` and every generic argument. Literal text, an arrow type and an
 * indexed access are read whole.
 *
 * Text no callback changes comes back byte for byte. A part a callback changed is printed again, a list through
 * TsTypeShape::listType() and an object with its own member separator.
 *
 * @phpstan-import-type TupleElement from TsTypeShape
 *
 * @phpstan-type ArmRewrite = Closure(string): ?string
 * @phpstan-type MemberRewrite = Closure(string, string): ?array{string, string}
 *
 * @internal
 */
final class TypeRewriter
{
    /** A type operator that reads the operand after it. */
    private const string PREFIX = '/^(?:keyof|typeof|readonly|unique)\s+/';

    /** The name of a generic type, maybe qualified, as TypeScript reads an identifier. */
    private const string GENERIC_NAME = '/^[\p{L}\p{Nl}$_][\p{L}\p{Nl}\p{Mn}\p{Mc}\p{Nd}\p{Pc}$.]*$/uD';

    /**
     * The type with each union arm at every depth passed to $arm once its inside is rewritten, and each object member
     * passed to $member once its value is; a null drops the arm or the member.
     *
     * A union left with no arm reads `unknown`. An arm whose rewritten text equals an earlier arm's is dropped when
     * either was changed, so two arms spelled alike that the rewrite left alone, two classes, both stay.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    public static function rewrite(string $type, ?Closure $arm = null, ?Closure $member = null): string
    {
        return $arm === null && $member === null ? $type : self::union($type, $arm, $member);
    }

    /**
     * The separator an object literal's members print with: `, ` where its top-level separators are all commas,
     * as an Inertia page's props print, else `; `.
     */
    public static function memberSeparator(string $object): string
    {
        $inner = substr($object, 1, -1);
        $commas = count(TsTypeShape::splitTopLevel($inner, [','])) > 1;

        return $commas && count(TsTypeShape::splitTopLevel($inner, [';'])) === 1 ? ', ' : '; ';
    }

    /**
     * A union position rewritten.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function union(string $type, ?Closure $arm, ?Closure $member): string
    {
        $arms = self::unionArms($type, $arm, $member);

        return $arms === null ? $type : self::joined($arms);
    }

    /**
     * A union's arms once rewritten, or null when the rewrite changed none.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     * @return list<string>|null
     */
    private static function unionArms(string $type, ?Closure $arm, ?Closure $member): ?array
    {
        $kept = [];
        $changed = false;

        foreach (TsTypeShape::splitTopLevel($type, ['|']) as $original) {
            $inside = self::armInside($original, $arm, $member);
            $rewritten = $arm === null ? $inside : $arm($inside);
            $differs = $rewritten !== $original;
            $changed = $changed || $differs;

            $alike = $rewritten !== null && array_any(
                $kept,
                static fn (array $earlier): bool => $earlier[0] === $rewritten && ($differs || $earlier[1]),
            );

            if ($rewritten !== null && ! $alike) {
                $kept[] = [$rewritten, $differs];
            }
        }

        return $changed ? array_column($kept, 0) : null;
    }

    /**
     * Union arms joined, `unknown` when none is left, each grouped once two or more join.
     *
     * @param  list<string>  $arms
     */
    private static function joined(array $arms): string
    {
        return match (count($arms)) {
            0 => 'unknown',
            1 => $arms[0],
            default => implode(' | ', array_map(TsTypeShape::unionArm(...), $arms)),
        };
    }

    /**
     * One union arm with its inside rewritten: each part of an intersection, or the arm as one operand.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function armInside(string $text, ?Closure $arm, ?Closure $member): string
    {
        // An arrow's return type takes in every `|` and `&` after it, so its signature is never split.
        if (TsTypeShape::unionArm($text) !== $text) {
            return $text;
        }

        $parts = TsTypeShape::splitTopLevel($text, ['&']);

        if (count($parts) < 2) {
            return self::operand($text, $arm, $member);
        }

        $rewritten = array_map(static fn (string $part): string => self::operand($part, $arm, $member), $parts);

        return $rewritten === $parts ? $text : implode(' & ', $rewritten);
    }

    /**
     * One operand, with no top-level union or intersection, rewritten.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function operand(string $text, ?Closure $arm, ?Closure $member): string
    {
        $code = TypeScanner::maskLiterals($text);

        if (preg_match(self::PREFIX, $code, $prefix) === 1) {
            $operand = substr($text, strlen($prefix[0]));
            $rewritten = self::operand($operand, $arm, $member);

            return $rewritten === $operand ? $text : $prefix[0].$rewritten;
        }

        return match (true) {
            $text !== '[]' && str_ends_with($code, '[]') => self::listed($text, $arm, $member),
            TsTypeShape::isWrapped($text, '(', ')') => self::grouped($text, $arm, $member),
            TsTypeShape::isWrapped($text, '[', ']') => self::tuple($text, $arm, $member),
            str_starts_with($code, '{') => self::object($text, $arm, $member),
            default => self::generic($text, $code, $arm, $member),
        };
    }

    /**
     * A `T[]` whose element union changed, printed again as a list of the arms left.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function listed(string $list, ?Closure $arm, ?Closure $member): string
    {
        $element = trim(substr($list, 0, -2));
        $union = TsTypeShape::isWrapped($element, '(', ')') ? trim(substr($element, 1, -1)) : $element;
        $arms = self::unionArms($union, $arm, $member);

        return $arms === null ? $list : TsTypeShape::listType($arms === [] ? ['unknown'] : $arms);
    }

    /**
     * A parenthesized type, its parentheses kept only while what is left still needs them.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function grouped(string $group, ?Closure $arm, ?Closure $member): string
    {
        $arms = self::unionArms(trim(substr($group, 1, -1)), $arm, $member);

        return $arms === null ? $group : TsTypeShape::postfixOperand(self::joined($arms));
    }

    /**
     * A tuple, each element rewritten, an optional element keeping its `?` and a rest element its `...`.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function tuple(string $tuple, ?Closure $arm, ?Closure $member): string
    {
        $elements = TsTypeShape::splitTopLevel(substr($tuple, 1, -1), [',']);
        $rewritten = array_map(
            static fn (string $element, array $read): string => self::element($element, $read, $arm, $member),
            $elements,
            TsTypeShape::tupleElements($tuple),
        );

        return $rewritten === $elements ? $tuple : '['.implode(', ', $rewritten).']';
    }

    /**
     * One tuple element rewritten, from its text and its type read without the optional marker.
     *
     * @param  TupleElement  $read
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function element(string $element, array $read, ?Closure $arm, ?Closure $member): string
    {
        [$type, $optional] = $read;

        if (str_starts_with($type, '...')) {
            $rest = substr($type, 3);
            $rewritten = self::union($rest, $arm, $member);

            return $rewritten === $rest ? $element : '...'.$rewritten;
        }

        $arms = self::unionArms($type, $arm, $member);

        if ($arms === null) {
            return $element;
        }

        return $optional ? TsTypeShape::postfixOperand(self::joined($arms)).'?' : self::joined($arms);
    }

    /**
     * An object literal, each member's value rewritten and then the member; any other text as it is.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function object(string $object, ?Closure $arm, ?Closure $member): string
    {
        $members = TsTypeShape::members($object);
        $printed = [];
        $changed = false;

        foreach ($members ?? [] as [$key, $value]) {
            $rewritten = self::union($value, $arm, $member);
            $kept = $member === null ? [$key, $rewritten] : $member($key, $rewritten);
            $changed = $changed || $kept === null || $kept[0] !== $key || $kept[1] !== $value;

            if ($kept !== null) {
                $printed[] = $kept[0].': '.$kept[1];
            }
        }

        return match (true) {
            ! $changed => $object,
            $printed === [] => '{}',
            default => '{ '.implode(self::memberSeparator($object), $printed).' }',
        };
    }

    /**
     * A generic type, each argument rewritten; any other text, a name or a literal, as it is.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function generic(string $text, string $code, ?Closure $arm, ?Closure $member): string
    {
        $open = strpos($code, '<');

        if ($open === false
            || preg_match(self::GENERIC_NAME, substr($text, 0, $open)) !== 1
            || ! TsTypeShape::isWrapped(substr($text, $open), '<', '>')) {
            return $text;
        }

        $arguments = TsTypeShape::splitTopLevel(substr($text, $open + 1, -1), [',']);
        $rewritten = array_map(static fn (string $argument): string => self::union($argument, $arm, $member), $arguments);

        return $rewritten === $arguments ? $text : substr($text, 0, $open).'<'.implode(', ', $rewritten).'>';
    }
}
