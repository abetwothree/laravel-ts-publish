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
 * Text no callback changes comes back byte for byte. An object or an intersection keeps every part a callback left
 * alone, its separators and its padding as written; a changed list prints through TsTypeShape::listType().
 *
 * @phpstan-import-type TupleElement from TsTypeShape
 *
 * @phpstan-type ArmRewrite = Closure(string): ?string
 * @phpstan-type MemberRewrite = Closure(string, string): ?array{string, string}
 * @phpstan-type Part = array{string, int, int}
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
     * A part the rewrite emptied to `{}`, as an open marker's own part is once the marker goes, leaves the
     * intersection while another part remains; a `{}` written as one stays.
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

        $parts = self::parts($text, ['&']);

        if (count($parts) < 2) {
            return self::operand($text, $arm, $member);
        }

        $rewritten = array_map(static function (array $part) use ($arm, $member): ?string {
            $operand = self::operand($part[0], $arm, $member);

            return $operand === '{}' && $part[0] !== '{}' ? null : $operand;
        }, $parts);

        return match (true) {
            $rewritten === array_column($parts, 0) => $text,
            array_filter($rewritten, is_string(...)) === [] => '{}',
            default => self::spliced($text, $parts, $rewritten),
        };
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
        $inner = substr($object, 1, -1);
        $parts = self::parts($inner, [';', ',']);

        if ($members === null || $parts === []) {
            return $object;
        }

        $rewritten = [];

        foreach ($members as $index => [$key, $value]) {
            $rewritten[] = self::member($parts[$index][0], $key, $value, $arm, $member);
        }

        return match (true) {
            $rewritten === array_column($parts, 0) => $object,
            array_filter($rewritten, is_string(...)) === [] => '{}',
            default => '{'.self::spliced($inner, $parts, $rewritten).'}',
        };
    }

    /**
     * One object member's text with its value rewritten and then the member, or null where the member is dropped.
     *
     * @param  ArmRewrite|null  $arm
     * @param  MemberRewrite|null  $member
     */
    private static function member(string $text, string $key, string $value, ?Closure $arm, ?Closure $member): ?string
    {
        $rewritten = self::union($value, $arm, $member);
        $kept = $member === null ? [$key, $rewritten] : $member($key, $rewritten);

        if ($kept === null || $kept === [$key, $value]) {
            return $kept === null ? null : $text;
        }

        // A member is its key, its colon and its value, so the text between them keeps its spacing.
        return $kept[0].substr($text, strlen($key), strlen($text) - strlen($key) - strlen($value)).$kept[1];
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
        $rewritten = array_map(
            static fn (string $argument): string => self::union($argument, $arm, $member),
            $arguments,
        );

        return $rewritten === $arguments ? $text : substr($text, 0, $open).'<'.implode(', ', $rewritten).'>';
    }

    /**
     * Each part between a text's depth-0 separators, as TsTypeShape::splitTopLevel() reads it, with where it stands.
     *
     * @param  list<string>  $separators
     * @return list<Part>
     */
    private static function parts(string $text, array $separators): array
    {
        $parts = [];
        $end = 0;

        foreach (TsTypeShape::splitTopLevel($text, $separators) as $part) {
            // Only separators and space stand before a part, so its first occurrence past the last part is the part.
            $start = (int) strpos($text, $part, $end);
            $parts[] = [$part, $start, $end = $start + strlen($part)];
        }

        return $parts;
    }

    /**
     * The text with each part replaced by its rewritten text, a dropped part leaving with the separator after it, or
     * before it where no kept part follows; what stands before the first part and after the last stays.
     *
     * @param  non-empty-list<Part>  $parts
     * @param  list<string|null>  $rewritten
     */
    private static function spliced(string $text, array $parts, array $rewritten): string
    {
        $last = array_key_last(array_filter($rewritten, is_string(...)));
        $printed = substr($text, 0, $parts[0][1]);

        foreach ($rewritten as $index => $part) {
            if ($part === null) {
                continue;
            }

            $printed .= $part;

            if ($index !== $last) {
                $printed .= substr($text, $parts[$index][2], $parts[$index + 1][1] - $parts[$index][2]);
            }
        }

        return $printed.substr($text, $parts[array_key_last($parts)][2]);
    }
}
