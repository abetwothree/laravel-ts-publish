<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Ops;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisMemo;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ArrayShape;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyPresence;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\KeyValue;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeKey;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeSignature;
use AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape\ShapeWide;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;

/**
 * Joins exclusive array shapes field by field, as PHPStan unions constant arrays: a key by KeyValue::joined(), absent
 * where a branch lacks it or joined with that branch's extras, signatures by pattern, extras and opaque parts unioned,
 * the wide data as MethodAnalysis::merge() merges it, and no arm carried. No named key is absorbed into the extras.
 *
 * @internal
 */
final class ShapeJoin
{
    /** The class maps a branch replaces and the queues it appends to, each key kept where it first stood. */
    private const array MERGED_MAPS = [...KeyValue::CLASS_MAPS, ...KeyValue::QUEUES];

    /**
     * Join exclusive branches, one of which runs, into one shape.
     *
     * @param  list<ArrayShape>  $branches
     */
    public static function exclusive(array $branches, JoinOptions $options): ArrayShape
    {
        if ($options->dropsUntypedBranches) {
            $branches = self::withUnalikeFillsUntyped($branches, $options);
        }

        /** @var array<array-key, non-empty-list<KeyValue>> $values each key's values, a numeric name as an int */
        $values = [];

        /** @var array<array-key, non-empty-list<int>> $holders the branch of each of those values */
        $holders = [];

        /** @var array<array-key, non-empty-list<KeyPresence>> $presences each named key's presence, as stated */
        $presences = [];
        $signatures = [];

        foreach ($branches as $index => $branch) {
            foreach (self::written($branch) as $entry) {
                $name = $entry->writtenName();
                $values[$name][] = $entry->value;
                $holders[$name][] = $index;
                $signatures[$name] = $entry instanceof ShapeSignature;

                if ($entry instanceof ShapeKey) {
                    $presences[$name][] = $entry->presence;
                }
            }
        }

        $entries = [];

        foreach ($values as $name => $keyValues) {
            $name = (string) $name;

            if ($signatures[$name]) {
                // An index signature never carries `?:`, a syntax error, whatever its branches say.
                $entries[] = new ShapeSignature($name, self::joinedValue($keyValues, $options)->withPresence(new KeyPresence));

                continue;
            }

            $presence = KeyPresence::join($presences[$name] ?? [], count($holders[$name]) === count($branches));
            $entries[] = new ShapeKey(
                $name, self::joinedValue(self::besideExtras($keyValues, $holders[$name], $branches), $options), $presence,
            );
        }

        return new ArrayShape(
            $entries,
            self::joinedExtras($branches, $options),
            array_merge(...array_map(static fn (ArrayShape $branch): array => $branch->opaque, $branches)),
            $branches !== [] && array_all($branches, static fn (ArrayShape $branch): bool => $branch->nonEmpty()->yes()),
            self::joinedWide($branches, $values),
        );
    }

    /**
     * One key's values across exclusive sources joined, each source a branch: a dropping join reads each value another
     * source spells unalike as untyped first, as exclusive() reads a branch's, then KeyValue::joined() joins them.
     *
     * @param  non-empty-list<KeyValue>  $values  each source's value, in the order of the options' returned values
     */
    public static function value(array $values, JoinOptions $options): KeyValue
    {
        $retypes = $options->dropsUntypedBranches
            ? self::unalikeRetypes(array_map(static fn (KeyValue $value): array => [$value], $values), null, $options)
            : [];

        foreach ($values as $index => $value) {
            if (isset($retypes[$index])) {
                $values[$index] = $value->untyped($retypes[$index]);
            }
        }

        return self::joinedValue($values, $options);
    }

    /**
     * Each key a branch sends once: a named key's last value, where its first write stood, and every signature entry.
     *
     * @return list<ShapeKey|ShapeSignature>
     */
    private static function written(ArrayShape $branch): array
    {
        $written = [];

        foreach ($branch->entries as $index => $entry) {
            $written[$entry instanceof ShapeSignature ? '#'.$index : '.'.$entry->name] = $entry;
        }

        return array_values($written);
    }

    /**
     * A key's values joined by KeyValue::joined() under the options' flags.
     *
     * @param  non-empty-list<KeyValue>  $values
     */
    private static function joinedValue(array $values, JoinOptions $options): KeyValue
    {
        return KeyValue::joined($values, $options->dropsUntypedBranches, $options->keepsLoneNull, $options->keepsFirstUntyped);
    }

    /**
     * A key's values with the extras of each branch that lacks it, which may hold it, in branch order.
     *
     * @param  non-empty-list<KeyValue>  $values
     * @param  non-empty-list<int>  $holders
     * @param  list<ArrayShape>  $branches
     * @return non-empty-list<KeyValue>
     */
    private static function besideExtras(array $values, array $holders, array $branches): array
    {
        if (count($holders) === count($branches) || array_all($branches, static fn (ArrayShape $branch): bool => $branch->extras === null)) {
            return $values;
        }

        $joined = [];
        $next = 0;

        foreach ($branches as $index => $branch) {
            if (in_array($index, $holders, true)) {
                $joined[] = $values[$next++];
            } elseif ($branch->extras !== null) {
                $joined[] = $branch->extras;
            }
        }

        return $joined === [] ? $values : $joined;
    }

    /**
     * The branches' extras joined, where any branch has them, each read as a source of one key.
     *
     * @param  list<ArrayShape>  $branches
     */
    private static function joinedExtras(array $branches, JoinOptions $options): ?KeyValue
    {
        $extras = array_values(array_filter(array_map(static fn (ArrayShape $branch): ?KeyValue => $branch->extras, $branches)));

        return $extras === [] ? null : self::value($extras, new JoinOptions(
            $options->dropsUntypedBranches, $options->keepsLoneNull, $options->keepsFirstUntyped,
        ));
    }

    /**
     * What the branches hold beside their keys, joined as MethodAnalysis::merge() merges them: imports, carried
     * classes, flags, the first type alias, a lone branch's variants, and each class map and queue in merge order, less
     * an entry under a joined key's name. A cast, slots or dotted-key close no key owns goes.
     *
     * @param  list<ArrayShape>  $branches
     * @param  array<array-key, mixed>  $named  the joined keys, by name
     */
    private static function joinedWide(array $branches, array $named): ShapeWide
    {
        $imports = [];
        $carried = [];
        $kept = [];
        $order = [];
        $alike = [];
        $loose = [];
        $flatTypeAlias = null;
        $flatTypeAliasFqcn = null;

        foreach ($branches as $branch) {
            $wide = $branch->wide;
            $own = $wide->customImports;

            foreach ($kept as $earlier) {
                foreach ($own as $path => $types) {
                    $own[$path] = array_values(array_diff($types, $earlier[$path] ?? []));
                }
            }

            $kept[] = $own;

            $imports = MethodAnalysis::importsWith($imports, $own);
            $carried = MethodAnalysis::carriedWith($carried, $wide->carried);

            foreach (self::MERGED_MAPS as $map) {
                $order[$map] = ($order[$map] ?? []) + array_fill_keys($wide->order[$map] ?? [], true);
            }

            // merge() drops the alike record of each key the branch holds without one before it lays the branch's own.
            foreach ($branch->entries as $entry) {
                if (! isset($entry->value->channels['alikeClassFqcns'])) {
                    unset($alike[$entry->writtenName()]);
                }
            }

            $alike += array_fill_keys($wide->order['alikeClassFqcns'] ?? [], true);
            $loose = ShapeWrites::looseMerged($loose, $wide->loose, dropsDeclared: true);
            $flatTypeAlias ??= $wide->flatTypeAlias;
            $flatTypeAliasFqcn ??= $wide->flatTypeAliasFqcn;
        }

        $order['alikeClassFqcns'] = $alike;

        return new ShapeWide(
            customImports: $imports,
            carried: $carried,
            readsCompletely: array_all($branches, static fn (ArrayShape $branch): bool => $branch->wide->readsCompletely),
            holdsUnreadKeys: array_any($branches, static fn (ArrayShape $branch): bool => $branch->wide->holdsUnreadKeys),
            holdsUnnamedKeys: array_any($branches, static fn (ArrayShape $branch): bool => $branch->wide->holdsUnnamedKeys),
            flatTypeAlias: $flatTypeAlias,
            flatTypeAliasFqcn: $flatTypeAliasFqcn,
            variants: count($branches) === 1 ? $branches[0]->wide->variants : null,
            order: array_filter(array_map(array_keys(...), $order)),
            loose: array_filter(array_map(static fn (array $entries): array => array_diff_key($entries, $named), $loose)),
        );
    }

    /**
     * The branches a dropping join reads, every write of a key another branch spells unalike in one of them read as the
     * untyped value it stood in for, so the join drops it. In the gap mode, a branch whose returned value the options
     * name reads as a gap there instead, which the join keeps beside the rest.
     *
     * @param  list<ArrayShape>  $branches
     * @return list<ArrayShape>
     */
    private static function withUnalikeFillsUntyped(array $branches, JoinOptions $options): array
    {
        /** @var array<array-key, array<int, non-empty-list<KeyValue>>> $rows every write of each key, by branch */
        $rows = [];

        foreach ($branches as $index => $branch) {
            foreach ($branch->entries as $entry) {
                $rows[$entry->writtenName()][$index][] = $entry->value;
            }
        }

        foreach ($rows as $name => $byBranch) {
            $retypes = self::unalikeRetypes($byBranch, (string) $name, $options);

            if ($retypes === []) {
                continue;
            }

            foreach ($branches as $index => $branch) {
                if (isset($retypes[$index])) {
                    $branches[$index] = self::retypedClassLess($branch, (string) $name, $retypes[$index]);
                }
            }
        }

        return $branches;
    }

    /**
     * The text each branch's writes of one key are read as where another branch spells one of them unalike: `unknown`,
     * or in the gap mode, a gap at the branch's returned value. Beside a typed value another branch keeps, the fill
     * left out at a returned value warns through DroppedUnionArms::leftBeside(), naming the key where one is given.
     *
     * @param  array<int, non-empty-list<KeyValue>>  $rows  every write of the key, by branch
     * @return array<int, string> by branch, each branch holding a write another spells unalike
     */
    private static function unalikeRetypes(array $rows, ?string $name, JoinOptions $options): array
    {
        $entry = static fn (KeyValue $value): array => [$value->type(), $value->channels['declaredTokenFqcns'] ?? []];
        $unalike = BranchJoin::declaredBranches(array_map($entry, array_merge(...array_values($rows))), true)[1];

        if ($unalike === []) {
            return [];
        }

        $fills = [];

        foreach ($rows as $index => $values) {
            $fill = array_find($values, static fn (KeyValue $value): bool => in_array($entry($value), $unalike, true));

            if ($fill !== null) {
                $fills[$index] = $fill;
            }
        }

        $typed = self::keepsTyped(array_merge([], ...array_values(array_diff_key($rows, $fills))), $options);
        $showsGaps = resolve(AnalysisMemo::class)->showsGaps();
        $scope = $options->scope;
        $retypes = [];

        foreach ($fills as $index => $fill) {
            $at = $options->returns[$index] ?? null;
            $id = match (true) {
                $scope === null || $at === null => null,
                $typed => DroppedUnionArms::leftBeside($scope, 'unalike-fill', $at, $fill->type(), $name),
                $showsGaps => DroppedUnionArms::gap($scope, 'unalike-fill', $at, $fill->type()),
                default => null,
            };
            $retypes[$index] = $id === null ? 'unknown' : VisibleGaps::arm($id);
        }

        return $retypes;
    }

    /**
     * Whether a join of values keeps a typed one once it drops the untyped: one beside `null`, or a `null` left alone
     * where the options keep it, as BranchJoin::slottedBranchUnion() reads them.
     *
     * @param  list<KeyValue>  $values
     */
    private static function keepsTyped(array $values, JoinOptions $options): bool
    {
        $typed = array_values(array_diff(
            array_unique(array_map(static fn (KeyValue $value): string => VisibleGaps::stripped($value->type())[0], $values)),
            ['unknown'],
        ));

        return $typed !== [] && ($options->keepsLoneNull || $typed !== ['null']);
    }

    /**
     * A branch with every write of a key retyped with a text naming no class, as MethodAnalysis::retypeClassLess()
     * retypes one: its channels forgotten, and each custom import only its old text spelled gone.
     */
    private static function retypedClassLess(ArrayShape $branch, string $name, string $type): ArrayShape
    {
        $spelled = [];
        $others = [$type];
        $entries = [];

        foreach ($branch->entries as $entry) {
            if ($entry->writtenName() !== $name) {
                $others[] = $entry->value->type();
                $entries[] = $entry;

                continue;
            }

            $spelled[] = $entry->value->type();
            $value = $entry->value->untyped($type);
            $entries[] = $entry instanceof ShapeKey
                ? new ShapeKey($entry->name, $value, $entry->presence, $entry->armOnly)
                : new ShapeSignature($entry->pattern, $value);
        }

        $imports = TokenSlots::keptImports($branch->wide->customImports, $spelled, $others);

        return new ArrayShape(
            $entries, $branch->extras, $branch->opaque, $branch->nonEmptyProof, $branch->wide->retyping($name, $imports),
        );
    }
}
