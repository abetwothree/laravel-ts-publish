<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;

/**
 * Leave out a union arm a sibling arm admits: `never[]` beside a single list arm, and a case literal beside an enum
 * alias whose cases include it. Only `never[]` and literal arms are ever dropped, so no class token leaves the type.
 *
 * @phpstan-type AliasLiterals = array<string, list<int|string>>
 *
 * @internal
 */
final class RedundantArms
{
    private const string EMPTY_LIST = 'never[]';

    /**
     * The type with every arm a sibling admits left out, at every depth.
     *
     * @param  AliasLiterals  $aliasLiterals  Each alias token with the case scalars it admits.
     */
    public static function absorbed(string $type, array $aliasLiterals = []): string
    {
        $mayAbsorb = str_contains($type, self::EMPTY_LIST)
            || array_any(array_keys($aliasLiterals), static fn (string $alias): bool => str_contains($type, $alias));

        return $mayAbsorb
            ? TypeRewriter::rewrite($type, union: static fn (array $arms): array => self::kept($arms, $aliasLiterals))
            : $type;
    }

    /**
     * One union's arms without each a sibling admits, in order; every arm when that would leave none.
     *
     * @param  list<string>  $arms
     * @param  AliasLiterals  $aliasLiterals
     * @return list<string>
     */
    public static function kept(array $arms, array $aliasLiterals = []): array
    {
        $kept = [];

        foreach ($arms as $index => $arm) {
            $admitted = array_any(
                $arms,
                static fn (string $by, int $at): bool => $at !== $index && self::admits($by, $arm, $aliasLiterals),
            );

            if (! $admitted) {
                $kept[] = $arm;
            }
        }

        return $kept === [] ? $arms : $kept;
    }

    /**
     * Whether an arm admits every value another arm spells, as this rule reads it.
     *
     * @param  AliasLiterals  $aliasLiterals
     */
    public static function admits(string $by, string $arm, array $aliasLiterals = []): bool
    {
        // tsc rejects `never[]` against a tuple, `[]`, a Record and an all-optional object, so only a list admits it.
        if ($arm === self::EMPTY_LIST) {
            return $by !== self::EMPTY_LIST
                && count(TsTypeShape::splitTopLevel($by, ['|', '&'])) === 1
                && TsTypeShape::elementType($by) !== null;
        }

        $literals = $aliasLiterals[$by] ?? [];

        return $literals !== [] && in_array(LiteralArm::value($arm), $literals, true);
    }
}
