<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Print\ShapeLowering;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeObserver;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
use PhpParser\Node;

/**
 * Joins one key's branch types into one type, beside the token slots a declared text's arms bring to it, and keeps the
 * casts every branch agrees on.
 *
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type CastInForce from MethodAnalysis
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type DeclaredArm from TokenSlots
 *
 * @internal
 */
final class BranchJoin
{
    /**
     * Join branch analyses into an empty target: a key some branch lacks is optional and absent, a value any branch may
     * remove stays so, channels merge as MethodAnalysis::merge() does, less each queue a branch only repeats of an
     * earlier one, a cast survives only where every branch setting its key casts it alike, flatTypeAlias is the first
     * non-null, and a lone branch's variants stay.
     *
     * A spread helper, a merge call and a literal's key with several sources drop an untyped branch
     * ($dropsUntypedBranches); only a merge call keeps the `null` left alone ($keepsLoneNull).
     *
     * A literal's key keeps `unknown` where its first branch is untyped, a held value PHP may still send
     * ($keepsFirstUntyped). Two classes one name spells, of any token kind, stay two arms, each read by its class.
     *
     * @param  list<MethodAnalysis>  $analyses
     * @param  list<Node>  $returns  each branch's returned value, where the branches are a method's returns
     */
    public static function joinInto(
        MethodAnalysis $target,
        array $analyses,
        bool $dropsUntypedBranches = false,
        bool $keepsLoneNull = false,
        bool $keepsFirstUntyped = false,
        array $returns = [],
        ?AnalysisScope $scope = null,
    ): void {
        if ($dropsUntypedBranches) {
            $analyses = self::withUnalikeFillsUntyped($analyses, $returns, $scope);
        }

        $branchCount = count($analyses);

        /** @var array<array-key, non-empty-list<KeyValue>> $values each branch's value of a key, by its name */
        $values = [];

        // The analysis-wide data, and each channel a key no branch writes keeps, merge as MethodAnalysis::merge()
        // merges them; each key's own channels are its joined value's.
        $channels = new MethodAnalysis;
        $flatTypeAlias = null;
        $flatTypeAliasFqcn = null;

        /** @var list<MethodAnalysis> $merged each branch as its channels merge, without the queues it only repeats */
        $merged = [];

        foreach ($analyses as $analysis) {
            // A key a branch writes twice, as a helper and a later key may, is one key it sends.
            foreach ($analysis->writtenProperties() as $prop) {
                $values[$prop['name']][] = KeyValue::of($analysis, $prop);
            }

            $branch = clone $analysis;

            foreach ($merged as $earlier) {
                $branch->dropRepeatsOf($earlier);
            }

            $merged[] = $branch;
            $channels->merge($branch);
            $channels->properties = [];

            $flatTypeAlias ??= $analysis->flatTypeAlias;
            $flatTypeAliasFqcn ??= $analysis->flatTypeAliasFqcn;
        }

        $properties = [];
        $untyped = [];
        $channels->casts = [];
        $channels->declaredTokenFqcns = [];
        $channels->closedToDottedKeys = [];

        foreach ($values as $name => $keyValues) {
            $name = (string) $name;
            $joined = KeyValue::joined($keyValues, $dropsUntypedBranches, $keepsLoneNull);
            // An index signature (e.g. `[key: number]`) never carries `?:`, a syntax error, whatever its branches say.
            $presence = JsEmitter::isIndexSignatureKey($name) ? new KeyPresence : KeyPresence::join(
                array_map(static fn (KeyValue $value): KeyPresence => $value->presence(), $keyValues),
                count($keyValues) === $branchCount,
            );
            $properties[] = MethodAnalysis::withPresence(
                ['name' => $name, ...$joined->row], $presence->optional, $presence->absent, $presence->removed,
            );
            ShapeLowering::layChannels($channels, $name, $joined);

            if ($keepsFirstUntyped && $keyValues[0]->type() === 'unknown') {
                $untyped[] = $name;
            }
        }

        $target->properties = $properties;
        $target->nestedResources = $channels->nestedResources;
        $target->customImports = $channels->customImports;
        $target->directEnumFqcns = $channels->directEnumFqcns;
        $target->modelFqcns = $channels->modelFqcns;
        $target->inlineEnumFqcns = $channels->inlineEnumFqcns;
        $target->inlineModelFqcns = $channels->inlineModelFqcns;
        $target->inlineResourceFqcns = $channels->inlineResourceFqcns;
        $target->alikeClassFqcns = $channels->alikeClassFqcns;
        $target->inlineEnumResourceFqcns = $channels->inlineEnumResourceFqcns;
        $target->wrappedEnums = $channels->wrappedEnums;
        $target->casts = $channels->casts;
        $target->carried = $channels->carried;
        $target->flatTypeAlias = $flatTypeAlias;
        $target->flatTypeAliasFqcn = $flatTypeAliasFqcn;
        $target->readsCompletely = $channels->readsCompletely;
        $target->holdsUnreadKeys = $channels->holdsUnreadKeys;
        $target->holdsUnnamedKeys = $channels->holdsUnnamedKeys;
        $target->closedToDottedKeys = $channels->closedToDottedKeys;
        $target->declaredTokenFqcns = $channels->declaredTokenFqcns;
        $target->variants = count($analyses) === 1 ? $analyses[0]->variants : null;

        // Untyped on the target, so the custom imports only the key's joined text spelled go with its classes.
        foreach ($untyped as $name) {
            $target->untype($name);
        }

        resolve(ShapeObserver::class)->observed($target);
    }

    /**
     * The branches a dropping join reads, each fill another branch spells unalike read as the untyped value it stood
     * in for, so the join drops it and publishes the other branches as it did before the fill. In the gap mode, a
     * branch whose returned value $returns names reads as a gap there instead, which the join keeps beside the rest.
     *
     * @template T of MethodAnalysis
     *
     * @param  list<T>  $analyses
     * @param  list<Node>  $returns  each branch's returned value, where the branches are a method's returns
     * @return list<T>
     */
    public static function withUnalikeFillsUntyped(array $analyses, array $returns = [], ?AnalysisScope $scope = null): array
    {
        $gapScope = $returns !== [] && resolve(AnalysisMemo::class)->showsGaps() ? $scope : null;

        /** @var array<array-key, list<array{string, Slots}>> $entries */
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
                        $analyses[$index]->retypeClassLess((string) $name, $gapScope !== null && isset($returns[$index])
                            ? VisibleGaps::arm(DroppedUnionArms::gap($gapScope, 'unalike-fill', $returns[$index], $prop['type']))
                            : 'unknown');

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

            $cast = self::agreedCast($branchCasts);

            if ($cast !== null) {
                $casts[(string) $name] = $cast;
            }
        }

        return $casts;
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
