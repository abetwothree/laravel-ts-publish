<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Support\TypeStrings;

/**
 * Leave out a union arm a sibling arm admits: `never[]` beside a single list arm or `[]`, and a case literal beside
 * an enum alias whose cases include it. Only `never[]` and literal arms are ever dropped, and an arm alike an earlier
 * one where the caller asks, so no class token leaves the type.
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
     * Two arms spelled alike stay, as two classes printed under one name may be, unless `$dedupesAlike` says the
     * caller knows each name spells one class: each union then keeps the first of its alike arms only.
     *
     * @param  AliasLiterals  $aliasLiterals  Each alias token with the case scalars it admits.
     */
    public static function absorbed(string $type, array $aliasLiterals = [], bool $dedupesAlike = false): string
    {
        return self::absorbedReporting($type, $aliasLiterals, $dedupesAlike)[0];
    }

    /**
     * The type absorbed() gives, and whether a case literal was among the arms it left out.
     *
     * @param  AliasLiterals  $aliasLiterals  Each alias token with the case scalars it admits.
     * @return array{string, bool}
     */
    public static function absorbedReporting(string $type, array $aliasLiterals = [], bool $dedupesAlike = false): array
    {
        $mayAbsorb = str_contains($type, self::EMPTY_LIST)
            || ($dedupesAlike && str_contains($type, '|'))
            || array_any(array_keys($aliasLiterals), static fn (string $alias): bool => str_contains($type, $alias));

        if (! $mayAbsorb) {
            return [$type, false];
        }

        $tookLiteral = false;
        $absorbed = TypeRewriter::rewrite($type, union: static function (array $arms) use ($aliasLiterals, $dedupesAlike, &$tookLiteral): array {
            $kept = self::kept($arms, $aliasLiterals);
            // Only never[] and literal arms are ever left out, and an admitted literal never has a kept twin.
            $tookLiteral = $tookLiteral
                || array_any(array_diff($arms, $kept), static fn (string $arm): bool => $arm !== self::EMPTY_LIST);

            return $dedupesAlike ? array_values(array_unique($kept)) : $kept;
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
     * The arms joined as one union, each once, without each a sibling admits.
     *
     * @param  list<string>  $arms
     */
    public static function joined(array $arms): string
    {
        return implode(' | ', self::kept(array_values(array_unique($arms))));
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
