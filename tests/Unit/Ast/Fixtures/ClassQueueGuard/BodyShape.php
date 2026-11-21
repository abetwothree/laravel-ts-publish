<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures\ClassQueueGuard;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use LogicException;

/**
 * Where a guard body puts its arms: a top-level ternary, the same under a key or in a list, through `when()` or through
 * `match`, a return of its own each, or a returned variable each arm overwrites on its flag. Each shape writes the
 * value and reads its union's members back out of the published type.
 */
enum BodyShape: string
{
    case Ternary = 'ternary';
    case UnderKey = 'under a key';
    case InList = 'in a list';
    case When = 'when()';
    case Match = 'match';
    case Returns = 'returns';
    case Writes = 'branch writes';

    private const array FLAGS = ['a', 'b', 'c'];

    /**
     * The statements of the generated resource's toArray(), its key `v` holding the arms in order.
     *
     * @param  non-empty-list<string>  $arms  PHP expressions.
     */
    public function body(array $arms): string
    {
        [$first, $rest] = [$arms[0], array_slice($arms, 1)];
        $flagged = static fn (string $statement, int $at): string => '        if ($this->resource->'.self::FLAGS[$at]
            .") {\n            {$statement}\n        }\n\n";

        return match ($this) {
            self::Returns => implode('', array_map(
                static fn (int $at, string $arm): string => $flagged("return ['v' => {$arm}];", $at),
                array_keys(array_slice($arms, 0, -1)),
                array_slice($arms, 0, -1),
            )).'        return [\'v\' => '.$arms[array_key_last($arms)].'];',
            self::Writes => "        \$data = ['v' => {$first}];\n\n".implode('', array_map(
                static fn (int $at, string $arm): string => $flagged("\$data['v'] = {$arm};", $at),
                array_keys($rest),
                $rest,
            )).'        return $data;',
            default => "        return ['v' => {$this->value($arms)}];",
        };
    }

    /**
     * The union members the shape's arms publish as, read out of the key's type; a member of another shape stays whole,
     * so its tokens fail the oracle.
     *
     * @return list<string>
     */
    public function members(string $type): array
    {
        $members = [];

        foreach (TsTypeShape::splitTopLevel($type, ['|']) as $member) {
            $inner = match ($this) {
                self::UnderKey => preg_match('/^\{\s*k:\s*(.*?);?\s*\}$/s', $member, $match) === 1 ? $match[1] : null,
                self::InList => self::listed($member),
                default => null,
            };

            array_push($members, ...($inner === null ? [$member] : TsTypeShape::splitTopLevel($inner, ['|'])));
        }

        return $members;
    }

    /**
     * A ternary over the box's flags from the $flag-th on, nested to the right: `$a ? A : ($b ? B : C)`.
     *
     * @param  list<string>  $rest  The arms after $first.
     */
    private static function ternary(string $first, array $rest, int $flag = 0): string
    {
        if ($rest === []) {
            return $first;
        }

        $next = self::ternary($rest[0], array_slice($rest, 1), $flag + 1);

        return '$this->resource->'.self::FLAGS[$flag]." ? {$first} : ".(count($rest) > 1 ? "({$next})" : $next);
    }

    /**
     * A match over the box's pick, the last arm its default.
     *
     * @param  non-empty-list<string>  $arms
     */
    private static function match(array $arms): string
    {
        $last = array_pop($arms);
        $cases = array_map(
            static fn (int $index, string $arm): string => "'".self::FLAGS[$index]."' => {$arm}, ",
            array_keys($arms),
            $arms,
        );

        return 'match ($this->resource->pick) { '.implode('', $cases)."default => {$last} }";
    }

    /**
     * The element type of a list member, `(A | B)[]` or `A[]`; null for any other member.
     */
    private static function listed(string $member): ?string
    {
        if (! str_ends_with($member, '[]')) {
            return null;
        }

        $element = substr($member, 0, -2);

        return str_starts_with($element, '(') && TsTypeShape::splitTopLevel($element, ['|']) === [$element]
            && str_ends_with($element, ')') ? substr($element, 1, -1) : $element;
    }

    /**
     * The value the generated resource's key holds, its arms in order, for a shape that writes one expression.
     *
     * @param  non-empty-list<string>  $arms  PHP expressions.
     */
    private function value(array $arms): string
    {
        [$first, $rest] = [$arms[0], array_slice($arms, 1)];

        return match ($this) {
            self::Ternary => self::ternary($first, $rest),
            self::UnderKey => "['k' => ".self::ternary($first, $rest).']',
            self::InList => '['.self::ternary($first, $rest).']',
            self::When => $rest === []
                ? "\$this->when(\$this->resource->a, {$first})"
                : "\$this->when(\$this->resource->a, {$first}, ".self::ternary($rest[0], array_slice($rest, 1), 1).')',
            self::Match => self::match($arms),
            self::Returns, self::Writes => throw new LogicException("A {$this->value} body writes no one value."),
        };
    }
}
