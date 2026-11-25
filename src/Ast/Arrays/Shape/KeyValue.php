<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\BranchJoin;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\TokenSlots;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ClassIdentity;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;

/**
 * One key's value: its row less its name, which the entry holding it keeps, and each channel map's entry under that
 * name, as MethodAnalysis::sliceOf() takes them. The row's `optional`, `absent` and `removed` are the presence it was
 * read or joined with; a ShapeKey holding the value states the key's own, which wins where they differ.
 *
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type CastInForce from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type Slots from TokenSlots
 * @phpstan-import-type HeldNulls from ExpressionHandler
 *
 * @phpstan-type KeyRow = array{
 *     type: string,
 *     optional: bool,
 *     description: string,
 *     bodyType?: string,
 *     fillType?: string,
 *     keepsKeys?: bool,
 *     splitOf?: string,
 *     absent?: true,
 *     removed?: true,
 * }
 * @phpstan-type KeyChannels = array{
 *     nestedResources?: class-string,
 *     directEnumFqcns?: class-string,
 *     modelFqcns?: class-string,
 *     inlineEnumFqcns?: list<class-string>,
 *     inlineModelFqcns?: list<class-string>,
 *     inlineResourceFqcns?: list<class-string>,
 *     inlineEnumResourceFqcns?: list<class-string>,
 *     wrappedEnums?: list<class-string>,
 *     casts?: CastInForce,
 *     declaredTokenFqcns?: Slots,
 *     alikeClassFqcns?: list<class-string>,
 *     heldObjectKeys?: HeldNulls,
 *     closedToDottedKeys?: true,
 * }
 * @phpstan-type EmbeddedClasses = array{
 *     nestedResources?: ClassMapType,
 *     directEnumFqcns?: ClassMapType,
 *     modelFqcns?: ClassMapType,
 * }
 *
 * @internal
 */
final readonly class KeyValue
{
    /** The queues a branch join keeps one copy of where two branches hold the key alike. */
    private const array QUEUES = [
        'inlineEnumFqcns', 'inlineModelFqcns', 'inlineResourceFqcns', 'inlineEnumResourceFqcns', 'wrappedEnums',
    ];

    /** Each kind's class map beside its queue, which a join splits a key's classes onto. */
    private const array SPLITS = [
        ['nestedResources', 'inlineResourceFqcns'],
        ['directEnumFqcns', 'inlineEnumFqcns'],
        ['modelFqcns', 'inlineModelFqcns'],
    ];

    /**
     * @param  KeyRow  $row  the row's fields but its name, in the order the row holds them
     * @param  KeyChannels  $channels  each channel map's entry under the key's name, where it holds one
     * @param  EmbeddedClasses  $embedded  the classes the key's queues hold that a class map keys by themselves
     */
    public function __construct(
        public array $row,
        public array $channels = [],
        public array $embedded = [],
    ) {}

    /**
     * One row of an analysis as a value, with every channel the analysis keeps under the row's name.
     *
     * @param  AnalyzedProperty  $row
     */
    public static function of(MethodAnalysis $analysis, array $row): self
    {
        $name = $row['name'];
        unset($row['name']);
        $channels = [];
        $embedded = [];

        if (isset($analysis->nestedResources[$name])) {
            $channels['nestedResources'] = $analysis->nestedResources[$name];
        }

        if (isset($analysis->directEnumFqcns[$name])) {
            $channels['directEnumFqcns'] = $analysis->directEnumFqcns[$name];
        }

        if (isset($analysis->modelFqcns[$name])) {
            $channels['modelFqcns'] = $analysis->modelFqcns[$name];
        }

        if (isset($analysis->inlineEnumFqcns[$name])) {
            $channels['inlineEnumFqcns'] = $analysis->inlineEnumFqcns[$name];
            $embedded['directEnumFqcns'] = array_intersect_key($analysis->directEnumFqcns, array_flip($channels['inlineEnumFqcns']));
        }

        if (isset($analysis->inlineModelFqcns[$name])) {
            $channels['inlineModelFqcns'] = $analysis->inlineModelFqcns[$name];
            $embedded['modelFqcns'] = array_intersect_key($analysis->modelFqcns, array_flip($channels['inlineModelFqcns']));
        }

        if (isset($analysis->inlineResourceFqcns[$name])) {
            $channels['inlineResourceFqcns'] = $analysis->inlineResourceFqcns[$name];
            $embedded['nestedResources'] = array_intersect_key($analysis->nestedResources, array_flip($channels['inlineResourceFqcns']));
        }

        if (isset($analysis->inlineEnumResourceFqcns[$name])) {
            $channels['inlineEnumResourceFqcns'] = $analysis->inlineEnumResourceFqcns[$name];
        }

        if (isset($analysis->wrappedEnums[$name])) {
            $channels['wrappedEnums'] = $analysis->wrappedEnums[$name];
        }

        if (isset($analysis->casts[$name])) {
            $channels['casts'] = $analysis->casts[$name];
        }

        if (isset($analysis->declaredTokenFqcns[$name])) {
            $channels['declaredTokenFqcns'] = $analysis->declaredTokenFqcns[$name];
        }

        if (isset($analysis->alikeClassFqcns[$name])) {
            $channels['alikeClassFqcns'] = $analysis->alikeClassFqcns[$name];
        }

        if (isset($analysis->heldObjectKeys[$name])) {
            $channels['heldObjectKeys'] = $analysis->heldObjectKeys[$name];
        }

        if (isset($analysis->closedToDottedKeys[$name])) {
            $channels['closedToDottedKeys'] = true;
        }

        return new self($row, $channels, array_filter($embedded));
    }

    /**
     * A value written to a key, as MethodAnalysis::addProperty() records it.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function ofResult(string $name, array $result): self
    {
        $slice = new MethodAnalysis;
        $slice->addProperty($name, $result);

        return self::of($slice, $slice->properties[0]);
    }

    /**
     * One key's values across exclusive branches joined, as a branch join joins them: the value typed as the
     * branches' union, or `unknown` where they spell a declared name unalike, every class each branch types it with
     * queued in branch order, and a cast only where every branch casts it alike. Its presence is KeyPresence::join()'s
     * as if every branch held the key; a caller with branches that lack it joins their presence again.
     *
     * A spread helper, a merge call and a literal's key with several sources drop an untyped branch
     * ($dropsUntypedBranches); only a merge call keeps the `null` left alone ($keepsLoneNull). A literal's key keeps
     * `unknown` where its first branch is untyped ($keepsFirstUntyped).
     *
     * @param  non-empty-list<self>  $values  each branch's value, in branch order
     */
    public static function joined(
        array $values,
        bool $dropsUntypedBranches = false,
        bool $keepsLoneNull = false,
        bool $keepsFirstUntyped = false,
    ): self {
        if ($dropsUntypedBranches) {
            $values = self::withUnalikeFillsUntyped($values);
        }

        $rows = array_column($values, 'row');
        $unalike = $keepsFirstUntyped && $rows[0]['type'] === 'unknown';
        // A dropping join has read an unalike fill as untyped already, so only a strict one meets one here.
        [$declared, $unalikeTexts] = BranchJoin::declaredBranches(array_map(
            static fn (self $value): array => [$value->row['type'], $value->channels['declaredTokenFqcns'] ?? []],
            $values,
        ), $dropsUntypedBranches);
        $bound = $unalikeTexts === [];
        [$type, $slots] = $bound
            ? BranchJoin::slottedBranchUnion(array_column($rows, 'type'), $dropsUntypedBranches, $keepsLoneNull, $declared)
            : ['unknown', []];
        $bodyTypes = array_map(static fn (array $row): string => $row['bodyType'] ?? $row['type'], $rows);
        $bodyType = $bound ? BranchJoin::branchUnion($bodyTypes, $dropsUntypedBranches, $keepsLoneNull, $declared) : $type;
        // A branch with no fill stands in with its own value. A fill equal to the merged type is kept too: that type
        // is a fill, and a reconcile that puts it back must find it again.
        $fillTypes = array_map(static fn (array $row): string => $row['fillType'] ?? $row['bodyType'] ?? $row['type'], $rows);
        $fillType = array_column($rows, 'fillType') === [] || ! $bound
            ? null
            : BranchJoin::branchUnion($fillTypes, $dropsUntypedBranches, $keepsLoneNull, $declared);
        $classed = array_map(static fn (self $value): array => $value->classed(), $values);
        $apart = null;

        // Text alone unions two same-named classes into one token, so the key reads its classes off their union.
        if (self::keepsClassesApart($type, $slots, $rows, $classed)) {
            $apart = ValueResult::unionResults($classed);
            $type = $apart['type'];
            $bodyType = $type;
            $fillType = $fillType === null ? null : $type;
        }

        $unalike = $unalike || ! $bound;
        $description = array_find($rows, static fn (array $row): bool => $row['description'] !== '')['description'] ?? '';
        $presence = KeyPresence::join(array_map(static fn (self $value): KeyPresence => $value->presence(), $values), true);
        $row = [
            'type' => $unalike ? 'unknown' : $type,
            'optional' => $presence->optional,
            'description' => $description,
            ...($bodyType === $type || $unalike ? [] : ['bodyType' => $bodyType]),
            ...($fillType === null || $unalike ? [] : ['fillType' => $fillType]),
            ...(array_all($rows, static fn (array $row): bool => $row['keepsKeys'] ?? false) ? ['keepsKeys' => true] : []),
            ...MethodAnalysis::presenceFacts($presence->absent, $presence->removed),
        ];
        [$channels, $embedded] = self::joinedChannels($values, $slots, $apart, $unalike);

        return new self($row, $channels, $embedded);
    }

    /**
     * The value's printed type.
     */
    public function type(): string
    {
        return $this->row['type'];
    }

    /**
     * The presence the value was read or joined with, as its row holds it.
     */
    public function presence(): KeyPresence
    {
        return KeyPresence::ofRow($this->row);
    }

    /**
     * Each branch value a join reads, each one another branch spells unalike read as the untyped value it stood in
     * for, so the join drops it and keeps the other branches.
     *
     * @param  non-empty-list<self>  $values
     * @return non-empty-list<self>
     */
    private static function withUnalikeFillsUntyped(array $values): array
    {
        $entries = array_map(
            static fn (self $value): array => [$value->row['type'], $value->channels['declaredTokenFqcns'] ?? []],
            $values,
        );
        $unalike = BranchJoin::declaredBranches($entries, true)[1];

        if ($unalike === []) {
            return $values;
        }

        foreach ($values as $index => $value) {
            if (in_array($entries[$index], $unalike, true)) {
                $values[$index] = $value->untyped();
            }
        }

        return $values;
    }

    /**
     * Whether a key's branch values, joined into $type, are joined again by class: where two classes of any token kind
     * print one name, and the join kept a type with no declared slots from fills that each repeat their branch's value.
     *
     * A strict join's `unknown` stands, as a union by class would drop the branch it stands for. Declared slots say
     * whose each token is themselves, and a fill another branch's value stands beside would lose its arm.
     *
     * @param  Slots  $slots
     * @param  list<KeyRow>  $rows
     * @param  list<ValueExpressionResult>  $classed
     */
    private static function keepsClassesApart(string $type, array $slots, array $rows, array $classed): bool
    {
        return $type !== 'unknown' && $slots === []
            && array_all($rows, static fn (array $row): bool => ($row['fillType'] ?? $row['type']) === $row['type'])
            && ClassIdentity::spellsTwoClassesAlike($classed);
    }

    /**
     * A key's channels past a join: each branch's merged, less the queues a branch only repeats of an earlier one
     * holding the key alike, a key's classes split onto its queue where branches type it with different classes, the
     * cast every branch agrees on, the joined slots, and the classes of the union where they are read off it.
     *
     * @param  non-empty-list<self>  $values
     * @param  Slots  $slots
     * @param  ValueExpressionResult|null  $apart
     * @return array{KeyChannels, EmbeddedClasses}
     */
    private static function joinedChannels(array $values, array $slots, ?array $apart, bool $unalike): array
    {
        $channels = [];
        $embedded = [];
        $earlier = [];

        foreach ($values as $value) {
            $own = $value->channels;

            foreach ($earlier as [$row, $queues]) {
                if ($row !== $value->row) {
                    continue;
                }

                foreach (self::QUEUES as $queue) {
                    if (isset($own[$queue]) && $own[$queue] === ($queues[$queue] ?? null)) {
                        unset($own[$queue]);
                    }
                }
            }

            $earlier[] = [$value->row, $own];
            $channels = self::mergedChannels($channels, $own);

            foreach ($value->embedded as $map => $classes) {
                $embedded[$map] = array_replace($embedded[$map] ?? [], $classes);
            }
        }

        unset($channels['casts'], $channels['declaredTokenFqcns'], $channels['closedToDottedKeys'], $channels['heldObjectKeys']);
        $cast = BranchJoin::agreedCast(
            array_map(static fn (self $value): ?array => $value->channels['casts'] ?? null, $values),
        );

        if ($cast !== null) {
            $channels['casts'] = $cast;
        }

        if (array_all($values, static fn (self $value): bool => isset($value->channels['closedToDottedKeys']))) {
            $channels['closedToDottedKeys'] = true;
        }

        if ($slots !== []) {
            $channels['declaredTokenFqcns'] = $slots;
        }

        [$channels, $embedded] = self::splitClasses($channels, $embedded, $values);

        if ($apart !== null) {
            [$channels, $embedded] = self::requeued($channels, $embedded, $apart);
        }

        return [$unalike ? self::classless($channels) : $channels, $embedded];
    }

    /**
     * One branch's channels merged over the joined ones: a class the later sets, a queue appended, its alike record
     * the later's or none.
     *
     * @param  KeyChannels  $channels
     * @param  KeyChannels  $own
     * @return KeyChannels
     */
    private static function mergedChannels(array $channels, array $own): array
    {
        foreach (['nestedResources', 'directEnumFqcns', 'modelFqcns'] as $map) {
            if (isset($own[$map])) {
                $channels[$map] = $own[$map];
            }
        }

        foreach (self::QUEUES as $queue) {
            if (isset($own[$queue])) {
                $channels[$queue] = [...$channels[$queue] ?? [], ...$own[$queue]];
            }
        }

        unset($channels['alikeClassFqcns']);

        if (isset($own['alikeClassFqcns'])) {
            $channels['alikeClassFqcns'] = $own['alikeClassFqcns'];
        }

        return $channels;
    }

    /**
     * The key's class of each kind moved onto its queue where the branches type it with different classes: every
     * branch's class there in branch order, each kept for its import under its own name, as an embedded class is.
     *
     * @param  KeyChannels  $channels
     * @param  EmbeddedClasses  $embedded
     * @param  non-empty-list<self>  $values
     * @return array{KeyChannels, EmbeddedClasses}
     */
    private static function splitClasses(array $channels, array $embedded, array $values): array
    {
        foreach (self::SPLITS as [$map, $queue]) {
            $classes = [];

            foreach ($values as $value) {
                if (isset($value->channels[$map])) {
                    $classes[$value->channels[$map]] = true;
                }
            }

            if (count($classes) < 2) {
                continue;
            }

            $queued = [];

            foreach ($values as $value) {
                if (isset($value->channels[$map])) {
                    $queued[] = $value->channels[$map];
                }

                array_push($queued, ...$value->channels[$queue] ?? []);
            }

            unset($channels[$map]);

            foreach (array_keys($classes) as $class) {
                $embedded[$map][$class] = $class;
            }

            $channels[$queue] = $queued;
        }

        return [$channels, $embedded];
    }

    /**
     * The key's import channels replaced with the ones its union carries, as MethodAnalysis::requeueChannels() lays
     * them, its cast, slots and dotted-key close kept.
     *
     * @param  KeyChannels  $channels
     * @param  EmbeddedClasses  $embedded
     * @param  ValueExpressionResult  $union
     * @return array{KeyChannels, EmbeddedClasses}
     */
    private static function requeued(array $channels, array $embedded, array $union): array
    {
        $channels = array_intersect_key($channels, array_flip(['casts', 'declaredTokenFqcns', 'closedToDottedKeys', 'heldObjectKeys']));
        $single = [
            'directEnumFqcns' => $union['directEnumFqcn'] ?? null,
            'nestedResources' => $union['resourceFqcn'] ?? null,
            'modelFqcns' => $union['modelFqcn'] ?? null,
        ];

        foreach ($single as $map => $class) {
            if ($class !== null) {
                $channels[$map] = $class;
            }
        }

        $queues = [
            'wrappedEnums' => $union['wrappedEnumFqcns'] ?? [],
            'alikeClassFqcns' => $union['alikeClassFqcns'] ?? [],
            'inlineEnumFqcns' => $union['embeddedEnumFqcns'] ?? [],
            'inlineModelFqcns' => $union['embeddedModelFqcns'] ?? [],
            'inlineResourceFqcns' => $union['embeddedResourceFqcns'] ?? [],
            'inlineEnumResourceFqcns' => $union['embeddedEnumResourceFqcns'] ?? [],
        ];

        foreach ($queues as $queue => $classes) {
            if ($classes !== []) {
                $channels[$queue] = $classes;
            }
        }

        foreach (['directEnumFqcns' => 'inlineEnumFqcns', 'modelFqcns' => 'inlineModelFqcns', 'nestedResources' => 'inlineResourceFqcns'] as $map => $queue) {
            foreach ($queues[$queue] as $class) {
                $embedded[$map][$class] = $class;
            }
        }

        return [$channels, $embedded];
    }

    /**
     * A key's channels without its import channels, cast, slots and alike record, as MethodAnalysis::forgetChannels()
     * leaves them.
     *
     * @param  KeyChannels  $channels
     * @return KeyChannels
     */
    private static function classless(array $channels): array
    {
        return array_intersect_key($channels, array_flip(['heldObjectKeys', 'closedToDottedKeys']));
    }

    /**
     * The value with the model and resource classes behind its tokens, as a ternary's arm has them.
     *
     * @return ValueExpressionResult
     */
    private function classed(): array
    {
        $channels = $this->channels;

        return [
            'type' => $this->row['type'],
            'optional' => false,
            ...(isset($channels['modelFqcns']) ? ['modelFqcn' => $channels['modelFqcns']] : []),
            ...(isset($channels['nestedResources']) ? ['resourceFqcn' => $channels['nestedResources']] : []),
            ...(($channels['inlineModelFqcns'] ?? []) !== [] ? ['embeddedModelFqcns' => $channels['inlineModelFqcns']] : []),
            ...(($channels['inlineResourceFqcns'] ?? []) !== [] ? ['embeddedResourceFqcns' => $channels['inlineResourceFqcns']] : []),
            ...(($channels['alikeClassFqcns'] ?? []) !== [] ? ['alikeClassFqcns' => $channels['alikeClassFqcns']] : []),
            ...(isset($channels['directEnumFqcns']) ? ['directEnumFqcn' => $channels['directEnumFqcns']] : []),
            ...(($channels['inlineEnumFqcns'] ?? []) !== [] ? ['embeddedEnumFqcns' => $channels['inlineEnumFqcns']] : []),
            ...(($channels['inlineEnumResourceFqcns'] ?? []) !== [] ? ['embeddedEnumResourceFqcns' => $channels['inlineEnumResourceFqcns']] : []),
            ...(($channels['wrappedEnums'] ?? []) !== [] ? ['wrappedEnumFqcns' => $channels['wrappedEnums']] : []),
        ];
    }

    /**
     * The value typed `unknown` with no class behind it, its own channels kept apart from its classes.
     */
    private function untyped(): self
    {
        $row = ['type' => 'unknown'] + $this->row;
        unset($row['bodyType'], $row['fillType']);

        return new self($row, self::classless($this->channels), $this->embedded);
    }
}
