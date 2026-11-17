<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

/**
 * Leave out a union arm a sibling arm admits: `never[]` beside a single list arm or `[]`, and a case literal beside
 * an enum alias whose cases include it. Only `never[]` and literal arms are ever dropped, so no class token leaves
 * the type.
 *
 * @phpstan-type AliasLiterals = array<string, list<int|string>>
 *
 * @internal
 */
final class RedundantArms
{
    private const string EMPTY_LIST = 'never[]';

    private const string EMPTY_TUPLE = '[]';

    /**
     * The type with every arm a sibling admits left out, at every depth.
     *
     * @param  AliasLiterals  $aliasLiterals  Each alias token with the case scalars it admits.
     */
    public static function absorbed(string $type, array $aliasLiterals = []): string
    {
        return self::absorbedReporting($type, $aliasLiterals)[0];
    }

    /**
     * The type absorbed() gives, and whether a case literal was among the arms it left out.
     *
     * @param  AliasLiterals  $aliasLiterals  Each alias token with the case scalars it admits.
     * @return array{string, bool}
     */
    public static function absorbedReporting(string $type, array $aliasLiterals = []): array
    {
        $mayAbsorb = str_contains($type, self::EMPTY_LIST)
            || array_any(array_keys($aliasLiterals), static fn (string $alias): bool => str_contains($type, $alias));

        if (! $mayAbsorb) {
            return [$type, false];
        }

        $tookLiteral = false;
        $absorbed = TypeRewriter::rewrite($type, union: static function (array $arms) use ($aliasLiterals, &$tookLiteral): array {
            $kept = self::kept($arms, $aliasLiterals);
            // Only never[] and literal arms are ever left out, and an admitted literal never has a kept twin.
            $tookLiteral = $tookLiteral
                || array_any(array_diff($arms, $kept), static fn (string $arm): bool => $arm !== self::EMPTY_LIST);

            return $kept;
        });

        return [$absorbed, $tookLiteral];
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
     * Whether $by admits every value $arm spells.
     *
     * @param  AliasLiterals  $aliasLiterals
     */
    public static function admits(string $by, string $arm, array $aliasLiterals = []): bool
    {
        // tsc rejects `never[]` against a tuple, a Record and an all-optional object. `[]` admits it, as both spell
        // only the empty array PHP sends.
        if ($arm === self::EMPTY_LIST) {
            return $by === self::EMPTY_TUPLE || ($by !== self::EMPTY_LIST && ArrayArms::listElement($by) !== null);
        }

        $literals = $aliasLiterals[$by] ?? [];

        return $literals !== [] && in_array(LiteralArm::value($arm), $literals, true);
    }
}
