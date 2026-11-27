<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\JoinOptions;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops\ShapeJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Read\ArrayShapes;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node;

/**
 * Joins one key's branch types into one type, beside the token slots a declared text's arms bring to it, and keeps the
 * cast every branch agrees on; joins branch analyses through ShapeJoin.
 *
 * @phpstan-import-type CastInForce from MethodAnalysis
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type DeclaredArm from TokenSlots
 *
 * @internal
 */
final class BranchJoin
{
    /**
     * Join branch analyses into an empty target, as ShapeJoin::exclusive() joins their shapes.
     *
     * A spread helper's returns, a merge call and a returned variable's branches drop an untyped branch
     * ($dropsUntypedBranches); the last two keep the `null` left alone ($keepsLoneNull).
     *
     * @param  list<MethodAnalysis>  $analyses
     * @param  list<Node>  $returns  each branch's returned value, where the branches are a method's returns
     */
    public static function joinInto(
        MethodAnalysis $target,
        array $analyses,
        bool $dropsUntypedBranches = false,
        bool $keepsLoneNull = false,
        array $returns = [],
        ?AnalysisScope $scope = null,
    ): void {
        ShapeLowering::into($target, ShapeJoin::exclusive(
            array_map(ArrayShapes::ofAnalysis(...), $analyses),
            new JoinOptions($dropsUntypedBranches, $keepsLoneNull, returns: $returns, scope: $scope),
        ));

        resolve(ShapeObserver::class)->observed($target);
    }

    /**
     * Each distinct text a key's branches declare token slots for, beside those slots, once branchUnion() has dropped
     * the branches it drops: those that join, and those another branch spells the own names of, as no join can then
     * say which class stands behind each of those tokens.
     *
     * @param  list<array{string, Slots}>  $entries  each branch's type for the key and the slots declared for it
     * @return array{list<array{string, Slots}>, list<array{string, Slots}>}
     */
    public static function declaredBranches(array $entries, bool $dropsUntypedBranches): array
    {
        // Two branches that differ only by a gap or the open marker are one text, as they are once both are stripped.
        $texts = array_map(static fn (array $entry): string => VisibleGaps::stripped($entry[0])[0], $entries);
        $bare = static fn (string $text, array $slots): string => serialize([VisibleGaps::stripped($text)[0], $slots]);

        if ($dropsUntypedBranches && count(array_unique($texts)) > 1) {
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

                if ($bare($other[0], $other[1]) !== $bare($own[0], $own[1]) && $spells) {
                    $unalike[] = $own;

                    continue 2;
                }
            }

            $joined[] = $own;
        }

        return [$joined, $unalike];
    }

    /**
     * The cast every branch holding a key casts it to alike, in one text and import: optional where any branch's says
     * so, else its flag only where every branch's cast sets the same one; null where a branch casts it otherwise or not
     * at all.
     *
     * @param  list<CastInForce|null>  $casts  each branch's cast of the key, in branch order
     * @return CastInForce|null
     */
    public static function agreedCast(array $casts): ?array
    {
        $cast = $casts[0] ?? null;

        if ($cast === null || ! array_all($casts, static fn (?array $branch): bool => $branch !== null
            && $branch['type'] === $cast['type'] && $branch['import'] === $cast['import'])) {
            return null;
        }

        return match (true) {
            array_any($casts, static fn (?array $branch): bool => ($branch['optional'] ?? null) === true) => [
                'type' => $cast['type'], 'import' => $cast['import'], 'optional' => true,
            ],
            array_all($casts, static fn (?array $branch): bool => $branch === $cast) => $cast,
            default => ['type' => $cast['type'], 'import' => $cast['import']],
        };
    }

    /**
     * One property's branch types as one type: the type itself when every branch agrees.
     *
     * @param  list<string>  $types
     * @param  list<array{string, Slots}>  $declared  declaredBranches()'s texts, whose arms join whole
     */
    public static function branchUnion(array $types, bool $dropsUntypedBranches, bool $keepsLoneNull, array $declared = []): string
    {
        return self::slottedBranchUnion($types, $dropsUntypedBranches, $keepsLoneNull, $declared)[0];
    }

    /**
     * branchUnion()'s type, beside the token slots the declared texts' arms bring to it, in print order.
     *
     * @param  list<string>  $types
     * @param  list<array{string, Slots}>  $declared
     * @return array{string, Slots}
     */
    public static function slottedBranchUnion(array $types, bool $dropsUntypedBranches, bool $keepsLoneNull, array $declared): array
    {
        $unique = array_values(array_unique($types));
        // Which branches count is decided on their text with gaps and the marker stripped; the union keeps each text.
        $bare = array_values(array_unique(array_map(static fn (string $type): string => VisibleGaps::stripped($type)[0], $unique)));

        if ($dropsUntypedBranches && count($bare) > 1) {
            $typed = array_values(array_diff($unique, ['unknown']));

            // A lone `null` left once the untypable branches are gone says nothing about a spread helper's value, but
            // a merge call keeps it, as a ternary keeps its one typed arm.
            $lone = array_values(array_diff($bare, ['unknown'])) === ['null'];
            $unique = $typed === [] || ($lone && ! $keepsLoneNull) ? ['unknown'] : $typed;
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
     * @return array{string, Slots}
     */
    private static function unionSlottedTypes(array $types, array $slotted): array
    {
        /** @var list<DeclaredArm> $joined */
        $joined = [];
        $nulls = [];

        foreach ($types as $type) {
            $typeArms = $slotted[$type]
                ?? array_map(static fn (string $arm): array => [$arm, []], TsTypeString::splitTopLevelUnion($type));

            foreach ($typeArms as $typeArm) {
                if ($typeArm[0] === 'null' || $typeArm[0] === VisibleGaps::DECLARED_NULL) {
                    $nulls[] = $typeArm[0];
                } elseif ($typeArm[1] !== [] || ! in_array($typeArm[0], array_column($joined, 0), true)) {
                    $joined[] = $typeArm;
                }
            }
        }

        $arms = array_column($joined, 0);
        $arms = $nulls === [] ? $arms : [...$arms, TsTypeString::hoistNull($nulls)];

        // Parentheses add no token, so the slots read the arms as they were declared.
        return [
            count($arms) === 1 ? $arms[0] : implode(' | ', array_map(TsTypeShape::unionArm(...), $arms)),
            TokenSlots::joinedSlots($joined),
        ];
    }
}
