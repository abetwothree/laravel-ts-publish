<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use BackedEnum;
use Closure;
use PhpParser\Node\Expr;
use ReflectionClass;

/**
 * Shared building blocks for ExpressionHandler results.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type HeldNulls from ExpressionHandler
 * @phpstan-import-type TypesImportMap from Datable
 * @phpstan-import-type CarriedMap from MethodAnalysis
 *
 * @phpstan-type AttributeChannels = array{
 *      enumFqcns: list<class-string>,
 *      classFqcns: list<class-string>,
 *      classTokenFqcns?: list<class-string|null>,
 *      customImports?: TypesImportMap,
 *      keepsKeys?: bool
 * }
 *
 * @internal
 */
final class ValueResult
{
    /** The members that send no value of their own: `null`, and the `[]` an enum wrap around null may be sent as. */
    public const array EMPTY_ARMS = ['null', 'never[]'];

    /** @var array<string, string> enum FQCN => the name its own file exports it under, read once per run */
    private static array $enumNames = [];

    /**
     * The fallback result for an expression that resolves to no useful type.
     *
     * @return ValueExpressionResult
     */
    public static function unknown(): array
    {
        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * A value as a template-literal index signature publishes it: never optional, its type admitting `undefined`.
     *
     * `[key: T]?:` is a syntax error, and a key matching the pattern is not guaranteed present.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function asIndexSignatureValue(array $result): array
    {
        return [...$result, 'type' => TsTypeString::orUndefined($result['type']), 'optional' => false];
    }

    /**
     * A type's top-level members except its empty ones, which an arm's shape and its enum are read past.
     *
     * @return list<string>
     */
    public static function filledArms(string $type): array
    {
        return array_values(array_diff(TsTypeString::splitTopLevelUnion($type), self::EMPTY_ARMS));
    }

    /**
     * Whether a type, its empty arms aside, ends in a list, as an enum wrap's or a direct read's shape is read.
     */
    public static function isListShaped(string $type): bool
    {
        return str_ends_with(implode(' | ', self::filledArms($type)), '[]');
    }

    /**
     * A list of a type, as TsTypeShape::listType() prints one.
     */
    public static function arrayWrapType(string $type): string
    {
        return TsTypeShape::listType([$type]);
    }

    /**
     * The value each element of a list result holds: $element with the list's channels, the one enum the list embeds
     * as the enum it holds where $element spells that enum's type, and the list's model only where $element names it,
     * alone or beside the `null` a shorter array pads with.
     *
     * @param  ValueExpressionResult  $list
     * @return ValueExpressionResult
     */
    public static function elementOf(array $list, string $element): array
    {
        $value = [...self::withoutHeldObject($list), 'type' => $element, 'optional' => false];
        unset($value['keepsKeys'], $value['absentReadsNull']);

        $enums = array_values(array_unique($list['embeddedEnumFqcns'] ?? []));

        if (count($enums) === 1 && self::enumTypeName($enums[0]) === $element) {
            $value['directEnumFqcn'] = $enums[0];
        }

        if (isset($value['modelFqcn']) && class_basename($value['modelFqcn']) !== TsTypeString::withoutNullArm($element)) {
            unset($value['modelFqcn']);
        }

        return $value;
    }

    /**
     * A collected list of an element, as a resource collection sends it: `Record<string, R>` when the collection keeps
     * its keys, else `R[]`. The one place that spells it, for `$this->collection` and `$this->resource`'s items alike.
     */
    public static function collectionListType(string $element, bool $keepsKeys): string
    {
        return $keepsKeys ? "Record<string, {$element}>" : TsTypeShape::listType([$element]);
    }

    /**
     * Whether a generated file exports every class a result names on its model channels, as far as this run knows.
     *
     * A token with no file behind it would be emitted without an import, so MethodAnalysis::addProperty() declines it.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function namesOnlyExportedClasses(array $result): bool
    {
        return array_all(self::modelChannelFqcns($result), PublishedClasses::exports(...));
    }

    /**
     * Whether every model a result names gets a published file. With no published set to read, a framework or
     * abstract model such as `Model` is still known to have none.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function namesOnlyPublishedModels(array $result): bool
    {
        return self::everyModelPublished(self::modelChannelFqcns($result));
    }

    /**
     * Whether every model of a list gets a published file, as namesOnlyPublishedModels() asks of a result's channels.
     *
     * @param  list<class-string>  $models
     */
    public static function everyModelPublished(array $models): bool
    {
        if (! array_all($models, PublishedClasses::exports(...))) {
            return false;
        }

        if (! PublishedModelRegistry::isEmpty()) {
            return true;
        }

        return ! array_any(
            $models,
            static fn (string $model): bool => FrameworkClasses::is($model) || new ReflectionClass($model)->isAbstract(),
        );
    }

    /**
     * Whether a reading can publish: there is one, it is not vague, and every model it names gets a published file.
     *
     * @param  ValueExpressionResult|null  $result
     *
     * @phpstan-assert-if-true !null $result
     */
    public static function isPublishableReading(?array $result): bool
    {
        return $result !== null && ! TsTypeString::isVagueTsType($result['type']) && self::namesOnlyPublishedModels($result);
    }

    /**
     * Carry a model attribute's FQCN channels onto the result that reads it, so every class, enum and `#[TsType]` name
     * the attribute's type spells keeps its import wherever the read is published.
     *
     * One FQCN of a kind rides its single-entry channel and several ride the embedded one, as `$this->attr` reads do.
     *
     * @param  ValueExpressionResult  $result
     * @param  AttributeChannels  $attribute
     * @return ValueExpressionResult
     */
    public static function withAttributeChannels(array $result, array $attribute): array
    {
        if (count($attribute['enumFqcns']) > 1) {
            $result['embeddedEnumFqcns'] = $attribute['enumFqcns'];
        } elseif ($attribute['enumFqcns'] !== []) {
            $result['directEnumFqcn'] = $attribute['enumFqcns'][0];
        }

        if (count($attribute['classFqcns']) > 1) {
            $result['embeddedModelFqcns'] = ClassTokenQueue::fqcnsOf($attribute);
        } elseif ($attribute['classFqcns'] !== []) {
            $result['modelFqcn'] = $attribute['classFqcns'][0];
        }

        if (($attribute['customImports'] ?? []) !== []) {
            $result['customImports'] = $attribute['customImports'];
        }

        if ($attribute['keepsKeys'] ?? false) {
            $result['keepsKeys'] = true;
        }

        return $result;
    }

    /**
     * The result as a key a placeholder such as a MissingValue can drop: optional, and out of `??`'s reach, since the
     * operator never meets the placeholder as null, so an absent request field's null no longer makes it required.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function missable(array $result): array
    {
        unset($result['absentReadsNull']);

        return [...$result, 'optional' => true];
    }

    /**
     * The result as an object PHP holds until its caller resolves it: a callable, or with $prop an Inertia prop.
     *
     * A callable whose resolved value may itself be a callable says so, since Inertia sends that one uncalled. A prop,
     * and a callable returning one, is closed to dotted keys: Inertia replaces it rather than opening it.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function heldObject(array $result, bool $prop = false): array
    {
        $returnsCallable = ! $prop && self::heldNullsOf($result) !== null && ! ($result['heldProp'] ?? false);
        $result = self::withoutHeldObject($result);

        return [
            ...$result,
            'heldObject' => true,
            ...($prop ? ['heldProp' => true, 'closedToDottedKeys' => $result['type']] : []),
            ...($returnsCallable ? ['returnsCallable' => true] : []),
        ];
    }

    /**
     * The value a call of a held callable returns, apart from the callable: a callable it may return marks it as one
     * that may hold an object.
     *
     * @param  ValueExpressionResult  $callable
     * @return ValueExpressionResult
     */
    public static function calledValue(array $callable): array
    {
        $value = self::withoutHeldObject($callable);
        $nullable = self::mayBeNull($value['type']);

        return ($callable['returnsCallable'] ?? false)
            ? [...$value, 'mayHoldObject' => ['objectNull' => $nullable, 'plainNull' => $nullable]]
            : $value;
    }

    /**
     * Where PHP may hold the value as an object and which arms may be null.
     *
     * Null where no arm is such an object.
     *
     * @param  ValueExpressionResult  $result
     * @return HeldNulls|null
     */
    public static function heldNullsOf(array $result): ?array
    {
        return match (true) {
            $result['heldObject'] ?? false => ['objectNull' => self::mayBeNull($result['type']), 'plainNull' => false],
            default => $result['mayHoldObject'] ?? null,
        };
    }

    /**
     * A key's value as `json_encode()` writes it and Inertia decodes it: an object PHP holds, a callable or an Inertia
     * prop, goes as `{}`, then `[]`, on every arm that holds one.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function heldObjectAsDecoded(array $result): array
    {
        if ($result['heldObject'] ?? false) {
            return [
                'type' => 'never[]',
                'optional' => false,
                'heldObject' => true,
                ...array_intersect_key($result, ['heldProp' => true, 'returnsCallable' => true]),
            ];
        }

        return isset($result['mayHoldObject']) && $result['type'] !== 'unknown'
            ? self::unionResults([$result, ['type' => 'never[]', 'optional' => false]], countMembers: true)
            : $result;
    }

    /**
     * The result marked as a value Inertia replaces before a dotted key writes under it, for as long as its type holds.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function closeToDottedKeys(array $result): array
    {
        return [...$result, 'closedToDottedKeys' => $result['type']];
    }

    /**
     * Whether Inertia replaces the value before a dotted key writes under it: marked so for this type, null aside.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function closedToDottedKeys(array $result): bool
    {
        $closed = $result['closedToDottedKeys'] ?? null;

        return $closed !== null && TsTypeString::withoutNullArm($closed) === TsTypeString::withoutNullArm($result['type']);
    }

    /**
     * Whether a published type may stand for null: it has a `null` arm, or is `unknown`.
     */
    public static function mayBeNull(string $type): bool
    {
        return $type === 'unknown' || TsTypeString::hasNullArm($type);
    }

    /**
     * The result without either held-object flag, as a value read from such an object is.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function withoutHeldObject(array $result): array
    {
        unset($result['heldObject'], $result['mayHoldObject'], $result['heldProp'], $result['returnsCallable']);

        return $result;
    }

    /**
     * The result as `??` or a short `?:` receives its checked operand: a field absent from the request reads `null`,
     * which the operator replaces, so only a placeholder that drops the key (a MissingValue) keeps it optional.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function checkedOperand(array $result): array
    {
        if ($result['absentReadsNull'] ?? false) {
            unset($result['absentReadsNull']);
            $result['optional'] = false;
        }

        return $result;
    }

    /**
     * Merge multiple branch expressions into a single union-typed ValueExpressionResult.
     *
     * Null returns (guard clauses) contribute `null` to the union instead of a full object shape;
     * duplicate types are removed and import metadata is collected from all branches.
     *
     * With $firstIsChecked, the first expression is the checked operand of a short `?:`; $resolve resolves each one in
     * the engine's place, as a closure reads its returns.
     *
     * @param  list<Expr>  $returns
     * @param  (Closure(Expr): ValueExpressionResult)|null  $resolve
     * @return ValueExpressionResult
     */
    public static function analyzeClosureUnion(
        array $returns,
        ExpressionEngine $engine,
        AnalysisScope $scope,
        bool $firstIsChecked = false,
        ?Closure $resolve = null,
    ): array {
        $results = array_map($resolve ?? $engine->resolve(...), $returns);

        if ($firstIsChecked) {
            $results[0] = self::checkedOperand($results[0]);
        }

        // Paired with their expressions here because unionResults() receives results only, and by then
        // the Expr an audit has to name is gone. The arm is still dropped, never widened to `unknown`.
        $dropped = [];

        foreach ($results as $index => $result) {
            if ($result['type'] === 'unknown') {
                DroppedUnionArms::record($returns[$index], $scope, 'closure-union');
                $dropped[] = $returns[$index];
            }
        }

        return DroppedUnionArms::warnOfPlaceholder(self::unionResults($results), $dropped, $scope);
    }

    /**
     * Merge already-resolved branch results into a single union-typed ValueExpressionResult.
     *
     * A branch the engine could not type is dropped rather than widening the union to `unknown` (D1);
     * a caller that resolves its arms under its own narrowing enters here instead of resolving twice.
     * With $countMembers, a union of enum resources is recognised by the count of its members, not of its arms' types.
     *
     * The union is optional when any arm, typed or not, is: an arm's `optional` means its key can be absent. An empty
     * `[]` beside a list is no member of its own, since the list already admits it. Its held-object flag is the arms',
     * and it is closed to dotted keys where every arm is, or is the `null` Inertia replaces as well.
     *
     * @param  list<ValueExpressionResult>  $results
     * @return ValueExpressionResult
     */
    public static function unionResults(array $results, bool $countMembers = false): array
    {
        /** @var list<string> $types the types mergeUnion() counts: each arm's whole type, or each member of it */
        $types = [];
        /** @var list<ValueExpressionResult> $branchResults every non-unknown branch, for channel merging */
        $branchResults = [];

        foreach ($results as $inner) {
            if ($inner['type'] === 'unknown') {
                continue;
            }

            array_push($types, ...($countMembers ? TsTypeString::splitTopLevelUnion($inner['type']) : [$inner['type']]));
            $branchResults[] = $inner;
        }

        $types = array_values(array_unique($types));
        $optional = array_any($results, fn (array $inner): bool => $inner['optional']);
        $closed = array_any($results, self::closedToDottedKeys(...))
            && array_all($results, fn (array $inner): bool => $inner['type'] === 'null' || self::closedToDottedKeys($inner));

        if ($types === []) {
            $unknown = [...self::unknown(), 'optional' => $optional];

            return self::withArmObjects($closed ? self::closeToDottedKeys($unknown) : $unknown, $results);
        }

        $result = self::mergeUnion($types, $branchResults);

        // Arms that spell one name for two classes cannot be told apart by their text, so the members are read again
        // arm by arm, one member per class.
        $result = self::spellsTwoClassesAlike($branchResults) ? self::withMembersByClass($result, $branchResults) : $result;

        $union = [...$result, 'type' => self::withoutEmptyListBesideList($result['type']), 'optional' => $optional];

        return self::withArmObjects($closed ? self::closeToDottedKeys($union) : $union, $results);
    }

    /**
     * Record which arm of a union that mixes an EnumResource wrap with a direct read of one enum is a list.
     *
     * The merged type spells both arms as one bare name, so only the arms' own results still tell them apart. The union
     * stays as it is when an arm carries both channels, or when the wrap arms, or the direct arms, disagree on their
     * shape. A Closure defers resolving the arms again until the union proves mixed, the only case worth the cost.
     *
     * @param  ValueExpressionResult  $union
     * @param  list<ValueExpressionResult>|Closure(): list<ValueExpressionResult>  $arms
     * @return ValueExpressionResult
     */
    public static function withEnumArmShapes(array $union, array|Closure $arms): array
    {
        if (! isset($union['enumFqcn'], $union['directEnumFqcn']) || $union['enumFqcn'] !== $union['directEnumFqcn']) {
            return $union;
        }

        /** @var list<bool> $wrapIsList one entry per arm that wraps */
        $wrapIsList = [];
        /** @var list<bool> $directIsList one entry per arm that reads directly */
        $directIsList = [];

        foreach ($arms instanceof Closure ? $arms() : $arms as $arm) {
            $wraps = isset($arm['enumFqcn']);
            $reads = self::readsEnumType($arm);

            // An arm carrying both is itself mixed, so it cannot say which of its members is the wrap.
            if ($wraps && $reads) {
                return $union;
            }

            $isList = self::isListShaped($arm['type']);

            if ($wraps) {
                $wrapIsList[] = $isList;
            } elseif ($reads) {
                $directIsList[] = $isList;
            }
        }

        $wrapShapes = array_values(array_unique($wrapIsList));
        $directShapes = array_values(array_unique($directIsList));

        if (count($wrapShapes) !== 1 || count($directShapes) !== 1) {
            return $union;
        }

        $union['wrapIsCollection'] = $wrapShapes[0];
        $union['directIsArray'] = $directShapes[0];

        return $union;
    }

    /**
     * The result's model queue, when two of its models share a name and the queue does not outrun the type's tokens.
     *
     * Reads the model channels only. ResultTypeInfoBridge hands the queue on as a type info's classTokenFqcns.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>|null
     */
    public static function modelQueueByToken(array $result): ?array
    {
        $models = self::modelChannelFqcns($result);
        $nameOf = class_basename(...);

        if (! self::sharesAName($models, $nameOf)) {
            return null;
        }

        $queue = new ClassTokenQueue($models, $nameOf);
        $queue->take($result['type']);

        return $queue->outrunsItsTokens() ? null : $models;
    }

    /**
     * The name an enum's type is spelled with.
     */
    public static function enumTypeName(string $fqcn): string
    {
        return self::enumConstName($fqcn).'Type';
    }

    /**
     * The name a backed enum's case-name union is spelled with: its published const name and `Kind`.
     */
    public static function enumKindName(string $fqcn): string
    {
        return self::enumConstName($fqcn).'Kind';
    }

    /**
     * The name an enum's const is spelled with, as the enum's own file exports it, whatever #[TsType] it carries.
     */
    public static function enumConstName(string $fqcn): string
    {
        // On a memo hit too: each consumer's cache entry depends on the files the name is read from.
        DependencyRecorder::recordClass($fqcn);

        return self::$enumNames[$fqcn] ??= TsNaming::enumName($fqcn);
    }

    /**
     * The names beside its type name that an enum's tokens are spelled with: a backed enum's `…Kind`.
     *
     * An import channel carries the enum behind each of these tokens too, so a queue reads them as the enum's own.
     *
     * @return list<string>
     */
    public static function enumSiblingNames(string $fqcn): array
    {
        return enum_exists($fqcn) && is_a($fqcn, BackedEnum::class, true) ? [self::enumKindName($fqcn)] : [];
    }

    /**
     * Whether a result spells its enum's `…Type`, the name the AsEnum rewrite of a wrap of the enum replaces.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function readsEnumType(array $result): bool
    {
        return isset($result['directEnumFqcn'])
            && TsTypeString::typeNameOccursIn(self::enumTypeName($result['directEnumFqcn']), $result['type']);
    }

    /**
     * Every name an enum's tokens are spelled with: its type name, then each sibling name.
     *
     * @return non-empty-list<string>
     */
    public static function enumNames(string $fqcn): array
    {
        return [self::enumTypeName($fqcn), ...self::enumSiblingNames($fqcn)];
    }

    /**
     * The classes behind a result's tokens, one queue per kind: its models, its resources and its enums read directly.
     *
     * @param  ValueExpressionResult  $result
     * @return array{ClassTokenQueue, ClassTokenQueue, ClassTokenQueue}
     */
    public static function tokenQueues(array $result): array
    {
        return [
            new ClassTokenQueue(self::modelChannelFqcns($result), class_basename(...)),
            new ClassTokenQueue(
                self::resourceChannelFqcns($result),
                static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn),
            ),
            self::enumTokens(self::directEnumChannelFqcns($result)),
        ];
    }

    /**
     * A queue of enum classes, each read by every name its tokens spell.
     *
     * @param  list<class-string>  $enums
     */
    public static function enumTokens(array $enums): ClassTokenQueue
    {
        return new ClassTokenQueue($enums, self::enumTypeName(...), self::enumSiblingNames(...));
    }

    /**
     * Forget each enum's name, so a run reads every enum's `#[TsEnum]` afresh.
     */
    public static function forgetEnumNames(): void
    {
        self::$enumNames = [];
    }

    /**
     * A union flagged `heldObject` where every arm is an object PHP holds, and `mayHoldObject` where only some are;
     * `heldProp` where every such object is an Inertia prop, and `returnsCallable` where a callable's call may be one.
     *
     * @param  ValueExpressionResult  $union
     * @param  list<ValueExpressionResult>  $arms
     * @return ValueExpressionResult
     */
    private static function withArmObjects(array $union, array $arms): array
    {
        $union = self::withoutHeldObject($union);
        $nulls = ['objectNull' => false, 'plainNull' => false];
        $someArm = false;
        $props = true;
        $returnsCallable = false;

        foreach ($arms as $arm) {
            $held = self::heldNullsOf($arm);
            $someArm = $someArm || $held !== null;
            $props = $props && ($held === null || ($arm['heldProp'] ?? false));
            $returnsCallable = $returnsCallable || ($arm['returnsCallable'] ?? false);
            $nulls = [
                'objectNull' => $nulls['objectNull'] || ($held['objectNull'] ?? false),
                'plainNull' => $nulls['plainNull'] || ($held === null ? self::mayBeNull($arm['type']) : $held['plainNull']),
            ];
        }

        if (! $someArm) {
            return $union;
        }

        $kinds = [...($props ? ['heldProp' => true] : []), ...($returnsCallable ? ['returnsCallable' => true] : [])];

        return array_all($arms, fn (array $arm): bool => $arm['heldObject'] ?? false)
            ? [...$union, 'heldObject' => true, ...$kinds]
            : [...$union, 'mayHoldObject' => $nulls, ...$kinds];
    }

    /**
     * A union's type without the `never[]` of an empty `[]` beside a list member, which already admits an empty array.
     */
    private static function withoutEmptyListBesideList(string $type): string
    {
        if (! str_contains($type, 'never[]')) {
            return $type;
        }

        $members = TsTypeString::splitTopLevelUnion($type);
        $lists = array_filter(
            $members,
            fn (string $member): bool => $member !== 'never[]' && TsTypeShape::elementType($member) !== null,
        );

        return $lists === [] || ! in_array('never[]', $members, true)
            ? $type
            : implode(' | ', array_diff($members, ['never[]']));
    }

    /**
     * Whether one name spells two different models, two different resources or two different enums read directly,
     * among the given results.
     *
     * @param  list<ValueExpressionResult>  $results
     */
    private static function spellsTwoClassesAlike(array $results): bool
    {
        /** @var list<class-string> $models */
        $models = [];
        /** @var list<class-string> $resources */
        $resources = [];
        /** @var list<class-string> $enums */
        $enums = [];

        foreach ($results as $result) {
            array_push($models, ...self::modelChannelFqcns($result));
            array_push($resources, ...self::resourceChannelFqcns($result));
            array_push($enums, ...self::directEnumChannelFqcns($result));
        }

        return self::sharesAName($models, class_basename(...))
            || self::sharesAName($resources, static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn))
            || (! self::wrapsAnEnum($results) && self::sharesAName($enums, self::enumTypeName(...)));
    }

    /**
     * Whether two different classes of the list are spelled with one name.
     *
     * @param  list<class-string>  $fqcns
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     */
    private static function sharesAName(array $fqcns, Closure $nameOf): bool
    {
        /** @var array<string, class-string> $firstOf name => the first class seen under it */
        $firstOf = [];

        foreach ($fqcns as $fqcn) {
            if (($firstOf[$nameOf($fqcn)] ??= $fqcn) !== $fqcn) {
                return true;
            }
        }

        return false;
    }

    /**
     * The FQCNs a result carries on its direct and embedded enum channels.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function directEnumChannelFqcns(array $result): array
    {
        return [...(isset($result['directEnumFqcn']) ? [$result['directEnumFqcn']] : []), ...($result['embeddedEnumFqcns'] ?? [])];
    }

    /**
     * Whether an arm wraps an enum resource. Its token holds no queue entry, since the AsEnum rewrite spells it by its
     * own import channel, so the union's enums cannot be read by class.
     *
     * @param  list<ValueExpressionResult>  $results
     */
    private static function wrapsAnEnum(array $results): bool
    {
        return array_any($results, fn (array $result): bool => isset($result['enumFqcn']) || isset($result['multiEnumResourceFqcns']));
    }

    /**
     * The FQCNs a result carries on its single and embedded model channels.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function modelChannelFqcns(array $result): array
    {
        return [...(isset($result['modelFqcn']) ? [$result['modelFqcn']] : []), ...($result['embeddedModelFqcns'] ?? [])];
    }

    /**
     * Fold union member types and their branch results into one ValueExpressionResult, carrying every
     * FQCN/import channel across so no emitted token loses its import.
     *
     * @param  list<string>  $types
     * @param  list<ValueExpressionResult>  $branchResults
     * @return ValueExpressionResult
     */
    private static function mergeUnion(array $types, array $branchResults): array
    {
        /** @var list<class-string> $enumResourceFqcns FQCNs from EnumResource::make() / new EnumResource() branches */
        $enumResourceFqcns = [];
        /** @var list<class-string> $enumDirectFqcns FQCNs from direct $this->prop enum-access branches */
        $enumDirectFqcns = [];
        /** @var list<class-string> $enumTypeReadFqcns the direct branches that spell their enum's type name */
        $enumTypeReadFqcns = [];
        /** @var list<class-string> $enumOtherReadFqcns the direct branches that spell no `…Type` */
        $enumOtherReadFqcns = [];
        // Never deduped: aliasPropertyType() walks this list positionally against left-to-right
        // occurrences of each bare enum name in the merged union's rendered type.
        /** @var list<class-string> $embeddedEnumFqcns FQCNs embedded inside nested inline-object types */
        $embeddedEnumFqcns = [];
        // One queue entry per rendered token, built in branch order because aliasPropertyType() walks it
        // against the rendered type left to right. A whole-branch model is appended in loop position, and
        // deduped: analyzeClosureUnion() collapses branches rendering the same string, leaving no token.
        /** @var list<class-string> $embeddedModelFqcns FQCN per model occurrence, in rendered order */
        $embeddedModelFqcns = [];
        /** @var list<class-string> $branchModelFqcns whole-branch model FQCNs already queued */
        $branchModelFqcns = [];
        /** @var list<class-string> $embeddedResourceFqcns */
        $embeddedResourceFqcns = [];
        /** @var TypesImportMap $customImports */
        $customImports = [];
        /** @var CarriedMap $carried */
        $carried = [];

        foreach ($branchResults as $inner) {
            // EnumResource branches are tracked apart from direct-access ones, so the result can
            // propagate the correct FQCN metadata.
            if (isset($inner['enumFqcn'])) {
                $enumResourceFqcns[] = $inner['enumFqcn'];
            }

            if (isset($inner['directEnumFqcn'])) {
                $enumDirectFqcns[] = $inner['directEnumFqcn'];

                if (self::readsEnumType($inner)) {
                    $enumTypeReadFqcns[] = $inner['directEnumFqcn'];
                } else {
                    $enumOtherReadFqcns[] = $inner['directEnumFqcn'];
                }
            }

            if (isset($inner['embeddedEnumFqcns'])) {
                array_push($embeddedEnumFqcns, ...$inner['embeddedEnumFqcns']);
            }

            if (isset($inner['modelFqcn']) && ! in_array($inner['modelFqcn'], $branchModelFqcns, true)) {
                $branchModelFqcns[] = $inner['modelFqcn'];
                $embeddedModelFqcns[] = $inner['modelFqcn'];
            }

            if (isset($inner['embeddedModelFqcns'])) {
                array_push($embeddedModelFqcns, ...$inner['embeddedModelFqcns']);
            }

            if (isset($inner['embeddedResourceFqcns'])) {
                array_push($embeddedResourceFqcns, ...$inner['embeddedResourceFqcns']);
            }

            if (isset($inner['resourceFqcn'])) {
                $embeddedResourceFqcns[] = $inner['resourceFqcn'];
            }

            foreach ($inner['customImports'] ?? [] as $path => $importTypes) {
                $customImports[$path] = [...($customImports[$path] ?? []), ...$importTypes];
            }

            foreach ($inner['carriedFqcns'] ?? [] as $kind => $fqcns) {
                $carried[$kind] = array_values(array_unique([...($carried[$kind] ?? []), ...$fqcns]));
            }
        }

        $result = ['type' => TsTypeString::hoistNull($types), 'optional' => false];

        $enumResourceFqcns = array_values(array_unique($enumResourceFqcns));
        $enumDirectFqcns = array_values(array_unique($enumDirectFqcns));
        // Safe to dedupe: one class per name reads alike at every entry, and two under one name are read by class
        // unless an arm outruns its tokens, where the merge by text stands.
        $embeddedResourceFqcns = array_values(array_unique($embeddedResourceFqcns));

        if ($enumResourceFqcns !== []) {
            // An arm spelling no `…Type`, such as a `…Kind` or an override, reads no type the AsEnum rewrite
            // replaces, so it rides the embedded queue.
            $enumDirectFqcns = array_values(array_unique($enumTypeReadFqcns));
            $embeddedEnumFqcns = [...$enumOtherReadFqcns, ...$embeddedEnumFqcns];
            $allBranchFqcns = array_values(array_unique([...$enumResourceFqcns, ...$enumDirectFqcns]));

            if ($enumDirectFqcns === [] && count($enumResourceFqcns) === 1) {
                // Pure EnumResource, single FQCN.
                $result['enumFqcn'] = $enumResourceFqcns[0];
            } elseif ($enumDirectFqcns !== [] && count($allBranchFqcns) === 1) {
                // Mixed: same FQCN via EnumResource and via direct access.
                $result['enumFqcn'] = $allBranchFqcns[0];
                $result['directEnumFqcn'] = $allBranchFqcns[0];
            } elseif ($enumDirectFqcns === []
                && count($enumResourceFqcns) > 1
                && count($enumResourceFqcns) === count($types)
            ) {
                // All non-null branches are EnumResource with different FQCNs.
                // Emit ordered list so the transformer can do per-token AsEnum rewrite.
                $result['multiEnumResourceFqcns'] = $enumResourceFqcns;
            } else {
                // Multiple different FQCNs or complex mixed branches: fall back to embedded imports.
                // Never deduped, same positional reasoning as $embeddedEnumFqcns's own @var above.
                $embeddedEnumFqcns = [...$allBranchFqcns, ...$embeddedEnumFqcns];
            }
        } elseif ($enumDirectFqcns !== []) {
            // Only direct-access enum branches: existing embedded behaviour. Never deduped,
            // same positional reasoning as $embeddedEnumFqcns's own @var above.
            $embeddedEnumFqcns = [...$enumDirectFqcns, ...$embeddedEnumFqcns];
        }

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        if ($embeddedModelFqcns !== []) {
            $result['embeddedModelFqcns'] = $embeddedModelFqcns;
        }

        if ($embeddedResourceFqcns !== []) {
            $result['embeddedResourceFqcns'] = $embeddedResourceFqcns;
        }

        if ($customImports !== []) {
            $result['customImports'] = $customImports;
        }

        if ($carried !== []) {
            $result['carriedFqcns'] = $carried;
        }

        // A resource's re-index skips the union only when every arm it could rewrite keeps its keys.
        $keyed = array_filter($branchResults, fn (array $inner): bool => TsTypeShape::holdsKeyedObject($inner['type']));

        if ($keyed !== [] && array_all($keyed, fn (array $inner): bool => $inner['keepsKeys'] ?? false)) {
            $result['keepsKeys'] = true;
        }

        if (array_any($branchResults, fn (array $inner): bool => $inner['unnamedKeys'] ?? false)) {
            $result['unnamedKeys'] = true;
        }

        // Every list arm keeps its int keys only where each branch spelling one says so.
        $lists = array_filter($branchResults, fn (array $inner): bool => ArrayArms::holdsList($inner['type']));

        if ($lists !== [] && array_all($lists, fn (array $inner): bool => $inner['listKeys'] ?? false)) {
            $result['listKeys'] = true;
        }

        return $result;
    }

    /**
     * Give a union one member per class where its arms spell one name for two: a member repeats an earlier one only
     * when its text and the classes behind its tokens both match, and the queues carry one FQCN per token, in order.
     * The merge by text stands when an arm's own queue does not line up with its tokens.
     *
     * @param  ValueExpressionResult  $union  the arms as merged by their text
     * @param  list<ValueExpressionResult>  $arms
     * @return ValueExpressionResult
     */
    private static function withMembersByClass(array $union, array $arms): array
    {
        /** @var array<string, string> $members identity => text */
        $members = [];
        $nullable = false;
        /** @var list<class-string> $modelFqcns */
        $modelFqcns = [];
        /** @var list<class-string> $resourceFqcns */
        $resourceFqcns = [];
        /** @var list<class-string> $enumFqcns */
        $enumFqcns = [];
        // A union wrapping an enum resource keeps the enum queue mergeUnion() built for the AsEnum rewrite.
        $wraps = self::wrapsAnEnum($arms);

        foreach ($arms as $arm) {
            [$models, $resources, $enums] = self::tokenQueues($arm);
            $enums = $wraps ? self::enumTokens([]) : $enums;

            foreach (TsTypeString::splitTopLevelUnion($arm['type']) as $member) {
                if ($member === 'null') {
                    $nullable = true;

                    continue;
                }

                $memberModels = $models->take($member);
                $memberResources = $resources->take($member);
                $memberEnums = $enums->take($member);
                $identity = implode("\0", [$member, ...$memberModels, '', ...$memberResources, '', ...$memberEnums]);

                if (isset($members[$identity])) {
                    continue;
                }

                $members[$identity] = $member;
                array_push($modelFqcns, ...$memberModels);
                array_push($resourceFqcns, ...$memberResources);
                array_push($enumFqcns, ...$memberEnums);
            }

            // An arm that queues a name for two classes more often than it spells it holds a class behind another's
            // token, and does not say which token is whose (a merge by text is such an arm): the merge by text stands.
            if ($models->outrunsItsTokens() || $resources->outrunsItsTokens() || $enums->outrunsItsTokens()) {
                return $union;
            }
        }

        unset($union['embeddedModelFqcns'], $union['embeddedResourceFqcns']);

        if ($enumFqcns !== []) {
            $union['embeddedEnumFqcns'] = $enumFqcns;
        }

        $union['type'] = implode(' | ', [...array_values($members), ...($nullable ? ['null'] : [])]);

        if ($modelFqcns !== []) {
            $union['embeddedModelFqcns'] = $modelFqcns;
        }

        if ($resourceFqcns !== []) {
            $union['embeddedResourceFqcns'] = $resourceFqcns;
        }

        return $union;
    }

    /**
     * The FQCNs a result carries on its single and embedded resource channels.
     *
     * @param  ValueExpressionResult  $result
     * @return list<class-string>
     */
    private static function resourceChannelFqcns(array $result): array
    {
        return [...(isset($result['resourceFqcn']) ? [$result['resourceFqcn']] : []), ...($result['embeddedResourceFqcns'] ?? [])];
    }
}
