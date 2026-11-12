<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Composition;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * Joins one key's branch types into one type, beside the token slots a declared text's arms bring to it, and keeps the
 * casts every branch agrees on.
 *
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type AnalyzedPropertyList from MethodAnalysis
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type DeclaredArm from TokenSlots
 *
 * @internal
 */
final class BranchJoin
{
    /**
     * Join branch analyses into an empty target: a key some branch lacks is optional and absent, a value any branch may
     * remove stays so, channels merge as MethodAnalysis::merge() does, a cast survives only where every branch setting
     * its key casts it alike, and flatTypeAlias is the first non-null.
     *
     * A spread helper, a merge call and a literal's key with several sources drop an untyped branch
     * ($dropsUntypedBranches); only a merge call keeps the `null` left alone ($keepsLoneNull).
     *
     * A literal's key keeps `unknown` where its first branch is untyped, a held value PHP may still send
     * ($keepsFirstUntyped), and two models or resources one name spells as two imported arms ($keepsClassesApart).
     *
     * @param  list<MethodAnalysis>  $analyses
     */
    public static function joinInto(
        MethodAnalysis $target,
        array $analyses,
        bool $dropsUntypedBranches = false,
        bool $keepsLoneNull = false,
        bool $keepsFirstUntyped = false,
        bool $keepsClassesApart = false,
    ): void {
        if ($dropsUntypedBranches) {
            $analyses = self::withUnalikeFillsUntyped($analyses);
        }

        $branchCount = count($analyses);

        /** @var array<array-key, list<AnalyzedProperty>> PHP stores a numeric name as an int key */
        $propertyMap = [];

        // Only the channels collapse onto merge(): a property name several branches set has to be
        // union-typed from $propertyMap below, which a field-by-field merge cannot express.
        $channels = new MethodAnalysis;
        $flatTypeAlias = null;
        $flatTypeAliasFqcn = null;

        /** @var array<array-key, bool> $closed whether every branch setting the key closes it */
        $closed = [];

        /** @var array<array-key, list<string>> $openReadings the readings any branch holds open under the key */
        $openReadings = [];

        /** @var array<array-key, list<array{string, Slots}>> $declaredMap each branch's type and declared slots */
        $declaredMap = [];

        /** @var array<array-key, list<ValueExpressionResult>> $classed each branch's value, with its classes */
        $classed = [];

        foreach ($analyses as $analysis) {
            // A key a branch writes twice, as a helper and a later key may, is one key it sends.
            foreach ($analysis->writtenProperties() as $prop) {
                $propertyMap[$prop['name']][] = $prop;
                $classed[$prop['name']][] = self::classedValue($analysis, $prop);
                $declaredMap[$prop['name']][] = [$prop['type'], $analysis->declaredTokenFqcns[$prop['name']] ?? []];
                $closed[$prop['name']] = ($closed[$prop['name']] ?? true) && isset($analysis->closedToDottedKeys[$prop['name']]);
                $openReadings[$prop['name']] = [...$openReadings[$prop['name']] ?? [], ...$analysis->openReadings[$prop['name']] ?? []];
            }

            $channels->merge($analysis);
            $channels->properties = [];

            $flatTypeAlias ??= $analysis->flatTypeAlias;
            $flatTypeAliasFqcn ??= $analysis->flatTypeAliasFqcn;
        }

        /** @var AnalyzedPropertyList */
        $properties = [];

        /** @var array<array-key, Slots> $slots */
        $slots = [];
        $unalike = [];

        foreach ($propertyMap as $name => $entries) {
            if ($keepsFirstUntyped && $entries[0]['type'] === 'unknown') {
                $unalike[] = (string) $name;
            }

            // A dropping join has read an unalike fill as untyped already, so only a strict one meets one here.
            [$declared, $unalikeTexts] = self::declaredBranches($declaredMap[$name], $dropsUntypedBranches);
            $bound = $unalikeTexts === [];
            [$type, $keySlots] = $bound
                ? self::slottedBranchUnion(array_column($entries, 'type'), $dropsUntypedBranches, $keepsLoneNull, $declared)
                : ['unknown', []];
            $bodyTypes = array_map(fn (array $e): string => $e['bodyType'] ?? $e['type'], $entries);
            $bodyType = $bound ? self::branchUnion($bodyTypes, $dropsUntypedBranches, $keepsLoneNull, $declared) : $type;
            // A branch with no fill stands in with its own value. A fill equal to the merged type is kept too: that
            // type is a fill, and a reconcile that puts it back must find it again.
            $fillTypes = array_map(fn (array $e): string => $e['fillType'] ?? $e['bodyType'] ?? $e['type'], $entries);
            $fillType = array_column($entries, 'fillType') === [] || ! $bound
                ? null
                : self::branchUnion($fillTypes, $dropsUntypedBranches, $keepsLoneNull, $declared);

            // Text alone unions two same-named classes into one token; queueSplitClasses() queues both in branch order.
            if ($keepsClassesApart && $bound && self::spellsClassesAlike($classed[$name])) {
                $type = ValueResult::unionResults($classed[$name])['type'];
                $bodyType = $type;
            }

            if ($keySlots !== []) {
                $slots[$name] = $keySlots;
            }

            if (! $bound) {
                $unalike[] = (string) $name;
            }

            $presentInAll = count($entries) === $branchCount;
            $keepsKeys = array_all($entries, fn (array $e): bool => $e['keepsKeys'] ?? false);
            $anyOptional = (bool) array_filter($entries, fn (array $e) => $e['optional']);
            // An index signature (e.g. `[key: number]`) can never carry `?:` — it's a syntax
            // error — regardless of whether every branch produced it.
            $signature = JsEmitter::isIndexSignatureKey($name);
            $optional = ! $signature && (! $presentInAll || $anyOptional);
            $absent = ! $presentInAll || array_any($entries, fn (array $e): bool => $e['absent'] ?? false);
            $removed = array_any($entries, fn (array $e): bool => $e['removed'] ?? false);

            // Use the first non-empty description found
            $description = '';

            foreach ($entries as $entry) {
                if ($entry['description'] !== '') { // @codeCoverageIgnoreStart
                    $description = $entry['description'];

                    break; // @codeCoverageIgnoreEnd
                }
            }

            $properties[] = [
                'name' => (string) $name,
                'type' => $type,
                'optional' => $optional,
                'description' => $description,
                ...($bodyType === $type ? [] : ['bodyType' => $bodyType]),
                ...($fillType === null ? [] : ['fillType' => $fillType]),
                ...($keepsKeys ? ['keepsKeys' => true] : []),
                ...MethodAnalysis::presenceFacts(! $signature && $absent, ! $signature && $removed),
            ];
        }

        $target->properties = $properties;
        $target->enumResources = $channels->enumResources;
        $target->nestedResources = $channels->nestedResources;
        $target->customImports = $channels->customImports;
        $target->directEnumFqcns = $channels->directEnumFqcns;
        $target->modelFqcns = $channels->modelFqcns;
        $target->inlineEnumFqcns = $channels->inlineEnumFqcns;
        $target->inlineModelFqcns = $channels->inlineModelFqcns;
        $target->inlineResourceFqcns = $channels->inlineResourceFqcns;
        $target->multiEnumResourceFqcns = $channels->multiEnumResourceFqcns;
        $target->inlineEnumResourceFqcns = $channels->inlineEnumResourceFqcns;
        $target->enumResourceArmShapes = $channels->enumResourceArmShapes;
        $target->casts = self::castsEveryBranchAgrees($analyses, array_keys($propertyMap));
        $target->carried = $channels->carried;
        $target->flatTypeAlias = $flatTypeAlias;
        $target->flatTypeAliasFqcn = $flatTypeAliasFqcn;
        $target->readsCompletely = $channels->readsCompletely;
        $target->holdsUnreadKeys = $channels->holdsUnreadKeys;
        $target->holdsUnnamedKeys = $channels->holdsUnnamedKeys;
        $target->closedToDottedKeys = array_fill_keys(array_keys(array_filter($closed)), true);
        $target->openReadings = array_map(static fn (array $shapes): array => array_values(array_unique($shapes)), array_filter($openReadings));
        $target->declaredTokenFqcns = $slots;
        $target->queueSplitClasses($analyses);

        // No class stands behind a token of a key its branches spell unalike, so the key, now unknown, keeps none.
        foreach ($unalike as $name) {
            $target->untype($name);
        }
    }

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
     * A branch's value for one key with the model and resource classes behind its tokens, as a ternary's arm has.
     *
     * @param  AnalyzedProperty  $property
     * @return ValueExpressionResult
     */
    private static function classedValue(MethodAnalysis $analysis, array $property): array
    {
        $name = $property['name'];

        return [
            'type' => $property['type'],
            'optional' => false,
            ...(isset($analysis->modelFqcns[$name]) ? ['modelFqcn' => $analysis->modelFqcns[$name]] : []),
            ...(isset($analysis->nestedResources[$name]) ? ['resourceFqcn' => $analysis->nestedResources[$name]] : []),
            ...(($analysis->inlineModelFqcns[$name] ?? []) !== [] ? ['embeddedModelFqcns' => $analysis->inlineModelFqcns[$name]] : []),
            ...(($analysis->inlineResourceFqcns[$name] ?? []) !== [] ? ['embeddedResourceFqcns' => $analysis->inlineResourceFqcns[$name]] : []),
        ];
    }

    /**
     * Whether a key's branch values spell two different models, or two different resources, with one name.
     *
     * @param  list<ValueExpressionResult>  $values
     */
    private static function spellsClassesAlike(array $values): bool
    {
        $models = [];
        $resources = [];

        foreach ($values as $value) {
            foreach ([...isset($value['modelFqcn']) ? [$value['modelFqcn']] : [], ...$value['embeddedModelFqcns'] ?? []] as $fqcn) {
                $models[class_basename($fqcn)][$fqcn] = true;
            }

            foreach ([...isset($value['resourceFqcn']) ? [$value['resourceFqcn']] : [], ...$value['embeddedResourceFqcns'] ?? []] as $fqcn) {
                $resources[TsNaming::resourceTypeName($fqcn)][$fqcn] = true;
            }
        }

        return array_any([...$models, ...$resources], static fn (array $fqcns): bool => count($fqcns) > 1);
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
        $arms = $nullable ? [...$arms, 'null'] : $arms;

        // Parentheses add no token, so the slots read the arms as they were declared.
        return [
            count($arms) === 1 ? $arms[0] : implode(' | ', array_map(TsTypeShape::unionArm(...), $arms)),
            TokenSlots::joinedSlots($joined),
        ];
    }
}
