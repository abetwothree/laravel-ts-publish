<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\DroppedUnionArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Enums\EnumNames;
use AbeTwoThree\LaravelTsPublish\Ast\Members\FrameworkClasses;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedModelRegistry;
use AbeTwoThree\LaravelTsPublish\Dtos\Contracts\Datable;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\ArrayArms;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\RedundantArms;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\VisibleGaps;
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
 * @phpstan-import-type PresenceFacts from MethodAnalysis
 *
 * @phpstan-type AttributeChannels = array{
 *      enumFqcns: list<class-string>,
 *      enumTokenFqcns?: list<class-string>,
 *      classFqcns: list<class-string>,
 *      classTokenFqcns?: list<class-string|null>,
 *      customImports?: TypesImportMap,
 *      keepsKeys?: bool,
 *      typedByClass?: true,
 *      wrappedEnumFqcns?: list<class-string>,
 *      wrapConstFqcns?: list<class-string>
 * }
 *
 * @internal
 */
final class ValueResult
{
    /** The members that send no value of their own: `null`, and the `[]` an enum wrap around null may be sent as. */
    public const array EMPTY_ARMS = ['null', 'never[]'];

    /** The keys of a result that say what the value is, not which classes or keys its printed objects hold. */
    private const array VALUE_FACTS = [
        'optional', 'absent', 'removed', 'unnamedKeys', 'listKeys', 'absentReadsNull', 'absentKeys', 'heldObject',
        'mayHoldObject', 'heldProp', 'returnsCallable', 'closedToDottedKeys', 'resolvedFqcn',
    ];

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
     * The value of a key an arm removes: PHP never sends it, so it prints as a key that may be absent, holding nothing.
     *
     * @return ValueExpressionResult
     */
    public static function removed(): array
    {
        return ['type' => 'never', 'optional' => true, 'removed' => true];
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
        return str_ends_with(implode(' | ', self::filledArms(VisibleGaps::aside($type)[0])), '[]');
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

        if (count($enums) === 1 && EnumNames::typeName($enums[0]) === $element) {
            $value['directEnumFqcn'] = $enums[0];
        }

        if (isset($value['modelFqcn'])
            && class_basename($value['modelFqcn']) !== TsTypeString::withoutNullArm(VisibleGaps::aside($element)[0])) {
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
     * A list result as json_encode() writes it once a step left its keys out of 0..n-1: ArrayArms::keyedList()'s type.
     *
     * @param  ValueExpressionResult  $list
     * @return ValueExpressionResult
     */
    public static function keyedList(array $list): array
    {
        return self::spelledTwice($list, ArrayArms::keyedList($list['type']));
    }

    /**
     * A result retyped by a rewrite of its text that may merge, drop or repeat arms, each queue kept to the tokens the
     * new text spells (ClassIdentity::retyped()), or the text alone where a token's class cannot be told; null where
     * the rewrite reads no type.
     *
     * @param  ValueExpressionResult  $result
     * @param  Closure(string): ?string  $rewrite
     * @return ValueExpressionResult|null
     */
    public static function retypedByClass(array $result, Closure $rewrite): ?array
    {
        $type = $rewrite($result['type']);

        if ($type === null) {
            return null;
        }

        $queuesNoClass = static fn (): bool => array_all(
            TokenKind::cases(),
            static fn (TokenKind $kind): bool => $kind->channelFqcns($result) === [],
        );

        if ($type === $result['type'] || $queuesNoClass()) {
            return [...$result, 'type' => $type];
        }

        return ClassIdentity::retyped($result, $rewrite) ?? [...$result, 'type' => $type];
    }

    /**
     * A result retyped by a text spelling its type's tokens twice, as `X[] | Record<string, X>` spells `X[]`'s: each
     * queue of the classes behind its tokens reads twice, or the second copy would take the last class of each name.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function spelledTwice(array $result, string $type): array
    {
        $twice = [...$result, 'type' => $type];

        if (($result['embeddedModelFqcns'] ?? []) !== []) {
            $twice['embeddedModelFqcns'] = [...$result['embeddedModelFqcns'], ...TokenKind::Model->channelFqcns($result)];
        }

        if (($result['embeddedResourceFqcns'] ?? []) !== []) {
            $twice['embeddedResourceFqcns'] = [...$result['embeddedResourceFqcns'], ...TokenKind::Resource->channelFqcns($result)];
        }

        if (($result['embeddedEnumFqcns'] ?? []) !== []) {
            $twice['embeddedEnumFqcns'] = [...$result['embeddedEnumFqcns'], ...TokenKind::Enum->channelFqcns($result)];
        }

        if (($result['embeddedEnumResourceFqcns'] ?? []) !== []) {
            $twice['embeddedEnumResourceFqcns'] = [...$result['embeddedEnumResourceFqcns'], ...$result['embeddedEnumResourceFqcns']];
        }

        if (($result['alikeClassFqcns'] ?? []) !== []) {
            $twice['alikeClassFqcns'] = [...$result['alikeClassFqcns'], ...$result['alikeClassFqcns']];
        }

        return $twice;
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
        return array_all(TokenKind::Model->channelFqcns($result), PublishedClasses::exports(...));
    }

    /**
     * Whether every model a result names gets a published file. With no published set to read, a framework or
     * abstract model such as `Model` is still known to have none.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function namesOnlyPublishedModels(array $result): bool
    {
        return self::everyModelPublished(TokenKind::Model->channelFqcns($result));
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
            $result['embeddedEnumFqcns'] = $attribute['enumTokenFqcns'] ?? $attribute['enumFqcns'];
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

        if ($attribute['typedByClass'] ?? false) {
            $result['typedByClass'] = true;
        }

        return self::withWrapChannels($result, $attribute);
    }

    /**
     * Carry the enums a type info's wraps send and the const behind each `typeof` token it spells onto a result.
     *
     * @param  ValueExpressionResult  $result
     * @param  array{wrappedEnumFqcns?: list<class-string>, wrapConstFqcns?: list<class-string>}  $info
     * @return ValueExpressionResult
     */
    public static function withWrapChannels(array $result, array $info): array
    {
        if (($info['wrappedEnumFqcns'] ?? []) !== []) {
            $result['wrappedEnumFqcns'] = $info['wrappedEnumFqcns'];
        }

        if (($info['wrapConstFqcns'] ?? []) !== []) {
            $result['embeddedEnumResourceFqcns'] = $info['wrapConstFqcns'];
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
     * The value one of several callables a caller may run returns: one's own value, else their union; `unknown` where
     * none is read or any one reads `unknown`, as unionResults() would drop that call's arm.
     *
     * @param  list<ValueExpressionResult>  $values
     * @return ValueExpressionResult
     */
    public static function unionOfCalls(array $values): array
    {
        return match (true) {
            count($values) === 1 => $values[0],
            $values === [] || array_any($values, static fn (array $value): bool => $value['type'] === 'unknown') => self::unknown(),
            default => self::unionResults($values),
        };
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
            ? self::unionResults([$result, ['type' => 'never[]', 'optional' => false]])
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

        return $closed !== null && TsTypeString::withoutNullArm(VisibleGaps::stripped($closed)[0])
            === TsTypeString::withoutNullArm(VisibleGaps::stripped($result['type'])[0]);
    }

    /**
     * The result as the one object its literal prints, whatever variants it holds, with that print's own classes, while
     * its type is still the union printed beside it; else the result as it is. A reader that takes a value's keys back
     * reads it through this, as the keys follow one object, not each variant's.
     *
     * @param  ValueExpressionResult  $result
     * @return ValueExpressionResult
     */
    public static function unsplit(array $result): array
    {
        $unsplit = $result['unsplit'] ?? null;

        if ($unsplit === null || ! self::isSplit($result)) {
            return $result;
        }

        $print = $unsplit['print'];
        // Facts recorded on the result for the print's own text since it was printed join the print's.
        $presence = $print['keyPresence'] ?? [];

        foreach ($result['keyPresence'] ?? [] as $object => $facts) {
            $presence[$object] = [...$presence[$object] ?? [], ...$facts];
        }

        $read = $print;

        // Only what the value itself is stays from the union: every class and key fact is the print's own.
        foreach (self::VALUE_FACTS as $fact) {
            if (array_key_exists($fact, $result)) {
                $read[$fact] = $result[$fact];
            }
        }

        return $presence === [] ? $read : [...$read, 'keyPresence' => $presence];
    }

    /**
     * Whether a result is a literal printed one object per variant, each an array PHP holds, while its type is still
     * that union.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function isSplit(array $result): bool
    {
        return ($result['unsplit']['of'] ?? null) === $result['type'];
    }

    /**
     * The type a reader that takes a value's keys back reads: its one-object print, as unsplit() reads it.
     *
     * @param  ValueExpressionResult  $result
     */
    public static function unsplitType(array $result): string
    {
        return self::unsplit($result)['type'];
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
     * What a read makes of a value with its top-level gap arms set aside, those gaps given back to the type it makes; a
     * read that declines stays null, and the gaps' warnings stay held for the strip or the extent that raises them.
     *
     * @param  ValueExpressionResult  $input
     * @param  Closure(ValueExpressionResult): (ValueExpressionResult|null)  $read
     * @return ValueExpressionResult|null
     */
    public static function gapTransparent(array $input, Closure $read): ?array
    {
        [$rest, $gaps] = VisibleGaps::aside($input['type']);

        if ($gaps === []) {
            return $read($input);
        }

        $result = $read([...$input, 'type' => $rest]);

        return $result === null ? null : [...$result, 'type' => VisibleGaps::restored($result['type'], $gaps)];
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
        $left = [];

        foreach ($results as $index => $result) {
            if ($result['type'] === 'unknown') {
                $left[] = [$returns[$index], 'closure-union'];
            }
        }

        return DroppedUnionArms::leftOut(self::unionResults($results), $left, $scope);
    }

    /**
     * Merge already-resolved branch results into a single union-typed ValueExpressionResult.
     *
     * A branch the engine could not type is dropped rather than widening the union to `unknown` (D1);
     * a caller that resolves its arms under its own narrowing enters here instead of resolving twice.
     *
     * The union is optional when any arm, typed or not, is: an arm's `optional` means its key can be absent. A
     * `never[]` beside a list or the empty tuple `[]` is no member of its own, as either admits it. Its held-object
     * flag is the arms', and it is closed to dotted keys where every arm is, or is the `null` Inertia replaces as well.
     *
     * @param  list<ValueExpressionResult>  $results
     * @return ValueExpressionResult
     */
    public static function unionResults(array $results): array
    {
        // Text alone keeps two same-named classes apart only while no strip collapses one of them onto the other.
        if (array_any($results, static fn (array $inner): bool => VisibleGaps::stripped($inner['type'])[0] !== $inner['type'])
            && ClassIdentity::spellsTwoClassesAlike(array_values(array_filter($results, static fn (array $inner): bool => $inner['type'] !== 'unknown')))) {
            return self::unionLiftingGaps($results);
        }

        /** @var list<string> $types each arm's type, which mergeUnion() splits into its members */
        $types = [];
        /** @var list<ValueExpressionResult> $branchResults every non-unknown branch, for channel merging */
        $branchResults = [];

        foreach ($results as $inner) {
            if ($inner['type'] === 'unknown') {
                continue;
            }

            $types[] = $inner['type'];
            $branchResults[] = $inner;
        }

        $types = array_values(array_unique($types));
        $optional = array_any($results, fn (array $inner): bool => $inner['optional']);
        $closed = array_any($results, self::closedToDottedKeys(...))
            && array_all($results, fn (array $inner): bool => VisibleGaps::aside($inner['type'])[0] === 'null'
                || self::closedToDottedKeys($inner));

        if ($types === []) {
            $unknown = [...self::unknown(), 'optional' => $optional];

            return self::withArmObjects($closed ? self::closeToDottedKeys($unknown) : $unknown, $results);
        }

        $result = self::mergeUnion($types, $branchResults);

        // Arms that spell one name for two classes cannot be told apart by their text, so the members are read again
        // arm by arm, one member per class.
        $result = ClassIdentity::spellsTwoClassesAlike($branchResults) ? ClassIdentity::membersByClass($result, $branchResults) : $result;

        $union = [...$result, 'type' => self::withTopLevelArmsAbsorbed($result['type']), 'optional' => $optional];
        $union = self::withArmObjects($closed ? self::closeToDottedKeys($union) : $union, $results);
        $presence = self::armsKeyPresence($results);

        if ($presence !== []) {
            $union['keyPresence'] = $presence;
        }

        return $union;
    }

    /**
     * The union of results spelling two classes alike, read on their texts with gaps and markers stripped, every gap
     * their arms held lifted to the union's top, where a strip leaves the arms spelled alike as they are.
     *
     * @param  list<ValueExpressionResult>  $results
     * @return ValueExpressionResult
     */
    private static function unionLiftingGaps(array $results): array
    {
        $gaps = [];
        $bare = [];

        foreach ($results as $inner) {
            [$type, $ids] = VisibleGaps::stripped($inner['type']);
            array_push($gaps, ...array_map(VisibleGaps::arm(...), $ids));
            $bare[] = [...$inner, 'type' => $type];
        }

        $union = self::unionResults($bare);

        return [...$union, 'type' => VisibleGaps::restored($union['type'], $gaps)];
    }

    /**
     * Each arm's record of why its object's optional keys may be missing, keyed by the object's own text, so a spread
     * of the union reads every arm as that arm was written; a text two arms record apart keeps both facts.
     *
     * @param  list<ValueExpressionResult>  $arms
     * @return array<string, array<string, PresenceFacts>>
     */
    private static function armsKeyPresence(array $arms): array
    {
        $presence = [];

        foreach ($arms as $arm) {
            foreach ($arm['keyPresence'] ?? [] as $object => $keys) {
                foreach ($keys as $name => $facts) {
                    $presence[$object][$name] = [...$presence[$object][$name] ?? [], ...$facts];
                }
            }
        }

        return $presence;
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
     * A union's type without each top-level arm a sibling admits, as RedundantArms reads it: `never[]` beside a list
     * or the empty tuple.
     *
     * An arm's inside is left to the publisher's pass, as armsKeyPresence() keys each arm's facts by its object's text.
     */
    private static function withTopLevelArmsAbsorbed(string $type): string
    {
        if (! str_contains($type, 'never[]')) {
            return $type;
        }

        $arms = TsTypeString::splitTopLevelUnion($type);
        $kept = RedundantArms::kept($arms);

        return $kept === $arms ? $type : implode(' | ', $kept);
    }

    /**
     * Whether a result carries an enum on any channel, read directly or wrapped in an enum resource.
     *
     * @param  ValueExpressionResult  $result
     */
    private static function carriesAnEnum(array $result): bool
    {
        return TokenKind::Enum->channelFqcns($result) !== []
            || TokenKind::WrapConst->channelFqcns($result) !== []
            || ($result['wrappedEnumFqcns'] ?? []) !== [];
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
        /** @var list<class-string> $enumDirectFqcns FQCNs from direct $this->prop enum-access branches */
        $enumDirectFqcns = [];
        // Never deduped: aliasPropertyType() walks this list positionally against left-to-right
        // occurrences of each bare enum name in the merged union's rendered type.
        /** @var list<class-string> $embeddedEnumFqcns FQCNs embedded inside nested inline-object types */
        $embeddedEnumFqcns = [];
        // Never deduped either: aliasTypeofConst() walks it against each `typeof X` an arm's object spells, in order.
        /** @var list<class-string> $embeddedEnumResourceFqcns the enum behind each wrap's const token */
        $embeddedEnumResourceFqcns = [];
        /** @var list<class-string> $wrappedEnumFqcns */
        $wrappedEnumFqcns = [];
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
            if (isset($inner['directEnumFqcn'])) {
                $enumDirectFqcns[] = $inner['directEnumFqcn'];
            }

            if (isset($inner['embeddedEnumFqcns'])) {
                array_push($embeddedEnumFqcns, ...$inner['embeddedEnumFqcns']);
            }

            array_push($embeddedEnumResourceFqcns, ...$inner['embeddedEnumResourceFqcns'] ?? []);
            array_push($wrappedEnumFqcns, ...$inner['wrappedEnumFqcns'] ?? []);

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

        $enumDirectFqcns = array_values(array_unique($enumDirectFqcns));
        // Safe to dedupe: one class per name reads alike at every entry, and two under one name are read by class
        // unless an arm outruns its tokens, where the merge by text stands.
        $embeddedResourceFqcns = array_values(array_unique($embeddedResourceFqcns));

        // Never deduped past the direct reads, same positional reasoning as $embeddedEnumFqcns's own @var above.
        $embeddedEnumFqcns = [...$enumDirectFqcns, ...$embeddedEnumFqcns];

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        // Two arms wrapping one enum print one token, so a const with one class behind it gets one entry per token.
        if ($embeddedEnumResourceFqcns !== []) {
            $result['embeddedEnumResourceFqcns'] = ClassTokenQueue::perToken(
                $embeddedEnumResourceFqcns,
                $result['type'],
                TokenKind::WrapConst->nameOf(...),
                anchor: TokenKind::WrapConst->anchor(),
            );
        }

        if ($wrappedEnumFqcns !== []) {
            $result['wrappedEnumFqcns'] = array_values(array_unique($wrappedEnumFqcns));
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

        // A literal beside an enum's scalar carries no enum, so only the arms that carry one decide.
        $enumArms = array_filter($branchResults, self::carriesAnEnum(...));

        if ($enumArms !== [] && array_all($enumArms, fn (array $inner): bool => $inner['sendsEnumScalar'] ?? false)) {
            $result['sendsEnumScalar'] = true;
        }

        // Every list arm keeps its int keys only where each branch spelling one says so.
        $lists = array_filter($branchResults, fn (array $inner): bool => ArrayArms::holdsList($inner['type']));

        if ($lists !== [] && array_all($lists, fn (array $inner): bool => $inner['listKeys'] ?? false)) {
            $result['listKeys'] = true;
        }

        return $result;
    }
}
