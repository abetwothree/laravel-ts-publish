<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;

/**
 * Joins one key's branch types into one type, beside the token slots a declared text's arms bring to it, and keeps the
 * casts every branch agrees on.
 *
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type TokenFqcns from MethodAnalysis
 * @phpstan-import-type DeclaredArm from TokenSlots
 *
 * @internal
 */
final class BranchJoin
{
    /**
     * The branches a dropping join reads, each fill another branch spells unalike read as the untyped value it stood
     * in for, so the join drops it and publishes the other branches as it did before the fill.
     *
     * @template T of MethodAnalysis
     *
     * @param  list<T>  $analyses
     * @return list<T>
     */
    public static function withUnalikeFillsUntyped(array $analyses): array
    {
        /** @var array<array-key, list<array{string, TokenFqcns}>> $entries */
        $entries = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->properties as $prop) {
                $entries[$prop['name']][] = [$prop['type'], $analysis->declaredTokenFqcns[$prop['name']] ?? []];
            }
        }

        foreach ($entries as $name => $keyEntries) {
            $unalike = self::declaredBranches($keyEntries, true)[1];

            if ($unalike === []) {
                continue;
            }

            foreach ($analyses as $index => $analysis) {
                $slots = $analysis->declaredTokenFqcns[$name] ?? [];

                foreach ($analysis->properties as $prop) {
                    if ($prop['name'] === (string) $name && in_array([$prop['type'], $slots], $unalike, true)) {
                        $analyses[$index] = clone $analysis;
                        $analyses[$index]->untype((string) $name);

                        break;
                    }
                }
            }
        }

        return $analyses;
    }

    /**
     * Each distinct text a key's branches declare token slots for, beside those slots, once branchUnion() has dropped
     * the branches it drops: those that join, and those another branch spells the own names of, as no join can then
     * say which class stands behind each of those tokens.
     *
     * @param  list<array{string, TokenFqcns}>  $entries  each branch's type for the key and the slots declared for it
     * @return array{list<array{string, TokenFqcns}>, list<array{string, TokenFqcns}>}
     */
    public static function declaredBranches(array $entries, bool $dropsUntypedBranches): array
    {
        if ($dropsUntypedBranches && count(array_unique(array_column($entries, 0))) > 1) {
            $entries = array_filter($entries, static fn (array $entry): bool => $entry[0] !== 'unknown');
        }

        $distinct = array_values(array_intersect_key($entries, array_unique(array_map(serialize(...), $entries))));
        $declared = array_values(array_filter($distinct, static fn (array $entry): bool => $entry[1] !== []));
        $joined = [];
        $unalike = [];

        foreach ($declared as $own) {
            foreach ($distinct as $other) {
                $spells = array_any(
                    TokenSlots::declaredTokens($other[0], $own[1]),
                    static fn (array $token): bool => $token['read'],
                );

                if ($other !== $own && $spells) {
                    $unalike[] = $own;

                    continue 2;
                }
            }

            $joined[] = $own;
        }

        return [$joined, $unalike];
    }

    /**
     * The cast entry of each key every branch that sets it casts to one text and import, as only then does the union
     * publish that cast. It is optional where any branch's cast says so, since the union is; otherwise it keeps a flag
     * only where every branch's cast sets the same one.
     *
     * @param  list<MethodAnalysis>  $analyses
     * @param  list<array-key>  $names
     * @return CastMap
     */
    public static function castsEveryBranchAgrees(array $analyses, array $names): array
    {
        $casts = [];

        foreach ($names as $name) {
            $branchCasts = [];

            foreach ($analyses as $analysis) {
                if (in_array((string) $name, array_column($analysis->properties, 'name'), true)) {
                    $branchCasts[] = $analysis->casts[$name] ?? null;
                }
            }

            $cast = $branchCasts[0] ?? null;

            if ($cast === null || ! array_all($branchCasts, static fn (?array $branchCast): bool => $branchCast !== null
                && $branchCast['type'] === $cast['type'] && $branchCast['import'] === $cast['import'])) {
                continue;
            }

            $anyOptional = array_any(
                $branchCasts,
                static fn (?array $branchCast): bool => ($branchCast['optional'] ?? null) === true,
            );

            $casts[(string) $name] = match (true) {
                $anyOptional => ['type' => $cast['type'], 'import' => $cast['import'], 'optional' => true],
                array_all($branchCasts, static fn (?array $branchCast): bool => $branchCast === $cast) => $cast,
                default => ['type' => $cast['type'], 'import' => $cast['import']],
            };
        }

        return $casts;
    }

    /**
     * One property's branch types as one type: the type itself when every branch agrees.
     *
     * @param  list<string>  $types
     * @param  list<array{string, TokenFqcns}>  $declared  declaredBranches()'s texts, whose arms join whole
     */
    public static function branchUnion(array $types, bool $dropsUntypedBranches, bool $keepsLoneNull, array $declared = []): string
    {
        return self::slottedBranchUnion($types, $dropsUntypedBranches, $keepsLoneNull, $declared)[0];
    }

    /**
     * branchUnion()'s type, beside the token slots the declared texts' arms bring to it, in print order.
     *
     * @param  list<string>  $types
     * @param  list<array{string, TokenFqcns}>  $declared
     * @return array{string, TokenFqcns}
     */
    public static function slottedBranchUnion(array $types, bool $dropsUntypedBranches, bool $keepsLoneNull, array $declared): array
    {
        $unique = array_values(array_unique($types));

        if ($dropsUntypedBranches && count($unique) > 1) {
            $typed = array_values(array_diff($unique, ['unknown']));

            // A lone `null` left once the untypable branches are gone says nothing about a spread helper's value, but
            // a merge call keeps it, as a ternary keeps its one typed arm.
            $unique = $typed === [] || ($typed === ['null'] && ! $keepsLoneNull) ? ['unknown'] : $typed;
        }

        $slotted = [];

        foreach ($declared as [$text, $slots]) {
            $slotted[$text] = TokenSlots::declaredArms($text, $slots);
        }

        if (count($unique) === 1) {
            return [$unique[0], array_merge([], ...array_column($slotted[$unique[0]] ?? [], 1))];
        }

        // `unknown` absorbs whatever it is unioned with, so one untypable branch makes the whole
        // union `unknown` — never a `string` that the untypable branch does not actually promise.
        if (in_array('unknown', $unique, true)) {
            return ['unknown', []];
        }

        return $slotted === [] ? [TsTypeString::hoistNull($unique), []] : self::unionSlottedTypes($unique, $slotted);
    }

    /**
     * Union branch types as hoistNull() does, but for a declared text's arms whose tokens take slots: two of one
     * spelling name different classes, as an override's and a model's do, so each is kept with its slots.
     *
     * @param  list<string>  $types
     * @param  array<string, list<DeclaredArm>>  $slotted  each declared text's arms
     * @return array{string, TokenFqcns}
     */
    private static function unionSlottedTypes(array $types, array $slotted): array
    {
        /** @var list<DeclaredArm> $joined */
        $joined = [];
        $nullable = false;

        foreach ($types as $type) {
            $typeArms = $slotted[$type]
                ?? array_map(static fn (string $arm): array => [$arm, []], TsTypeString::splitTopLevelUnion($type));

            foreach ($typeArms as $typeArm) {
                if ($typeArm[0] === 'null') {
                    $nullable = true;
                } elseif ($typeArm[1] !== [] || ! in_array($typeArm[0], array_column($joined, 0), true)) {
                    $joined[] = $typeArm;
                }
            }
        }

        $arms = array_column($joined, 0);

        return [implode(' | ', $nullable ? [...$arms, 'null'] : $arms), TokenSlots::joinedSlots($joined)];
    }
}
