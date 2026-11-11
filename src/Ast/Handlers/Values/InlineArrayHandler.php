<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers\Values;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralArms;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Fold\LiteralWrites;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\Reading;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\UnreadSpreads;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ObjectMembers;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\PhpArrayKey;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use AbeTwoThree\LaravelTsPublish\Support\TypeStrings\IndexSignatureKey;
use Closure;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\String_;

/**
 * An inline array literal: `['name' => $this->resource->name, 'value' => $this->maxSizeMb()]` becomes
 * `{ name: string; value: number }`, a list such as `[$a, $b]` a list type. Spreads of a bare named resource, a bound
 * model's or collection's toArray(), or any other value typed as one object fold into intersection arms.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type InlineSpreadArm from InlineObjectResult
 * @phpstan-import-type ObjectSpreadArm from InlineObjectResult
 * @phpstan-import-type KeyedValues from ObjectMembers
 *
 * @internal
 */
final class InlineArrayHandler implements ExpressionHandler
{
    use InspectsAstNodes;

    /**
     * Classify a spread's value expression as a bare named resource, a bound model's or collection's toArray(), or
     * any other value typed as one object: the arms an inline array's intersection is built from. Shared with
     * ResourceAstAnalyzer's top-level flatten path, so the two never diverge on the same shape.
     *
     * @return InlineSpreadArm|ObjectSpreadArm|null
     */
    public static function classifySpreadArm(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $modelFqcn = self::spreadModelToArrayFqcn($expr, $scope);

        if ($modelFqcn !== null) {
            return ['fqcn' => $modelFqcn, 'isModel' => true, 'isCollection' => false];
        }

        $collectionFqcn = self::spreadCollectionToArrayFqcn($expr, $scope);

        if ($collectionFqcn !== null) {
            return ['fqcn' => $collectionFqcn, 'isModel' => true, 'isCollection' => true];
        }

        $chain = self::spreadRelationChainFqcn($expr, $scope);

        if ($chain !== null) {
            return ['fqcn' => $chain['fqcn'], 'isModel' => true, 'isCollection' => $chain['isCollection']];
        }

        $spreadResult = $engine->resolve($expr);

        if (isset($spreadResult['resourceFqcn'])
            && $spreadResult['type'] === TsNaming::resourceTypeName($spreadResult['resourceFqcn'])) {
            return ['fqcn' => $spreadResult['resourceFqcn'], 'isModel' => false, 'isCollection' => false];
        }

        // Spreading null throws, so a null arm sets no key. A class no generated file exports would be a token with no
        // import behind it. A resolved resource's restated keys are one object, though an intersection.
        $type = TsTypeString::withoutNullArm($spreadResult['type']);

        return (self::isObjectType($type) || isset($spreadResult['resolvedFqcn'])) && ValueResult::namesOnlyExportedClasses($spreadResult)
            ? ['type' => $type, 'result' => $spreadResult]
            : null;
    }

    /**
     * An array literal's items with each spread of a literal keyed only by names PHP keeps strings replaced by that
     * literal's own items, which PHP writes where the spread stands. A spread of any other value stays, as does a
     * literal $staysSpread takes by its index.
     *
     * @param  array<ArrayItem>  $items
     * @param  (Closure(int): bool)|null  $staysSpread
     * @return list<ArrayItem>
     */
    public static function withNamedLiteralSpreadsInlined(array $items, ?Closure $staysSpread = null): array
    {
        $inlined = [];

        foreach (array_values($items) as $index => $item) {
            if ($item->unpack && $item->value instanceof Array_ && self::namesEveryKey($item->value)
                && ($staysSpread === null || ! $staysSpread($index))) {
                array_push($inlined, ...$item->value->items);

                continue;
            }

            $inlined[] = $item;
        }

        return $inlined;
    }

    /**
     * Whether the item loops read a spread by the value it holds: not a `$this->`, `self::`, `static::` or `parent::`
     * call nor a call of the class's own method they flatten themselves, a model's or collection's toArray() whose arm
     * the print names, nor the keys a returned variable's walk holds already. A PHP function's value is read.
     */
    public static function readsSpreadByValue(Expr $value, AnalysisScope $scope): bool
    {
        $relative = $value instanceof StaticCall && $value->class instanceof Name
            && in_array($value->class->toLowerString(), ['self', 'static', 'parent'], true);
        $ownCall = $value instanceof FuncCall
            && (! $value->name instanceof Name || $scope->declaringClass->hasMethod($value->name->getLast()));

        return ! AnalysisScope::isWalkAlias($value) && ! $relative && ! $ownCall
            && ! ($value instanceof MethodCall && $value->var instanceof Variable && $value->var->name === 'this')
            && self::spreadModelToArrayFqcn($value, $scope) === null
            && self::spreadCollectionToArrayFqcn($value, $scope) === null
            && self::spreadRelationChainFqcn($value, $scope) === null;
    }

    /**
     * The keys a spread writes where it stands, each with its value: where the value reads as one object, or as a union
     * of objects, whose named keys the engine reads, maybe beside `[]`; null for any other spread.
     *
     * An open value's named keys are written too, the object it lands in left open for the rest. A union writes per
     * key its arms' union, one some arm lacks a key it may leave unset; a `?` member reads as writtenMembers() has it.
     *
     * The item loop writes these keys over the keys before the spread, so planArms() keeps no arm for it.
     *
     * @param  ValueExpressionResult  $result  the spread's value
     * @return KeyedValues|null
     */
    public static function spreadKeys(Expr $value, array $result, AnalysisScope $scope): ?array
    {
        $arms = TsTypeString::splitTopLevelUnion(TsTypeString::withoutNullArm($result['type']));
        $objects = array_values(array_diff($arms, ['never[]']));

        if ($objects === [] || ! self::readsWholeValue($value, $result, $scope)) {
            return null;
        }

        $armKeys = [];

        foreach ($objects as $object) {
            $named = self::namedObject($object);
            $keys = $named === null
                ? null
                : ObjectMembers::seededWrittenMembers([...$result, 'type' => $named], $scope->carriesImports);

            // PHP renumbers a key it stores as an int, so the spread writes it under another key.
            if ($keys === null || array_any($keys, static fn (array $key): bool => PhpArrayKey::storedInt($key[0]) !== null)) {
                return null;
            }

            $armKeys[] = $keys;
        }

        $keys = count($armKeys) === 1 ? $armKeys[0] : self::unionKeys($armKeys);

        return count($objects) === count($arms) ? $keys : ObjectMembers::eachOptional($keys);
    }

    /**
     * The value a spread may write under any key, where its value reads as one map, maybe beside `[]`: a
     * `Record<string, …>`, or an object holding only a `[key: string]` signature; null for any other spread.
     *
     * @param  ValueExpressionResult  $result  the spread's value
     * @return ValueExpressionResult|null the map's value, with the classes behind its tokens but no custom import
     */
    public static function spreadSignature(Expr $value, array $result, AnalysisScope $scope): ?array
    {
        $map = self::spreadObject($result);
        $mapValue = $map !== null && self::readsWholeValue($value, $result, $scope) ? self::mapValue($map) : null;

        // Read as a one-key object's member, the value takes the classes behind its own tokens.
        $members = $mapValue === null
            ? null
            : ObjectMembers::seededMembers([...$result, 'type' => '{ value: '.$mapValue.' }'], $scope->carriesImports);

        return $members === null ? null : $members[0][1];
    }

    /**
     * Record the keys a keyless spread writes where it stands, an open value's named ones included, else each held key
     * a map may write; true where the keys were recorded in place. A value that holds keys no name names, which PHP
     * writes too, leaves the object it is written into open.
     *
     * @param  ValueExpressionResult  $result  the spread's value
     */
    public static function recordSpread(
        LiteralWrites $writes,
        Expr $value,
        array $result,
        AnalysisScope $scope,
        int $position,
        bool $printedArm = false,
    ): bool {
        $keys = self::spreadKeys($value, $result, $scope);

        if ($keys === null) {
            $signature = self::spreadSignature($value, $result, $scope);

            if ($signature !== null) {
                $writes->recordMapSpread($signature, $result, $position);
            }

            return false;
        }

        $writes->recordKeys($keys, $result, $position, $printedArm);

        if (! $printedArm && self::holdsUnnamedKeys($result)) {
            $writes->holdUnnamedKeys();
            UnreadSpreads::record();
        }

        return true;
    }

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [Array_::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof Array_) {
            return $this->analyzeInlineArray($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Plan which spreads of a nested literal its print keeps as arms, each spread classified once: one whose keys the
     * item loop writes in place, or a literal one, stays an arm only where a later arm follows, as the print places
     * every arm before the keys and a later arm wins.
     *
     * @param  array<ArrayItem>  $items  the literal's items
     */
    public function planArms(array $items, AnalysisScope $scope, ExpressionEngine $engine): LiteralArms
    {
        $read = [];

        // A literal naming every key is inlined, or stays a spread only where a later arm follows it too.
        foreach ($items as $item) {
            if ($this->mayPrintAsArm($item) && ! ($item->value instanceof Array_ && self::namesEveryKey($item->value))) {
                $read[spl_object_id($item)] = self::readArm($item, $scope, $engine);
            }
        }

        $staysArm = array_map(static fn (array $spread): bool => $spread['arm'] !== null
            && (! isset($spread['arm']['result']) || (! $spread['inPlace'] && $spread['mapValue'] === null)), $read);
        $followsOriginal = self::armsFollow(array_values($items), $staysArm);
        $inlined = self::withNamedLiteralSpreadsInlined($items, static fn (int $index): bool => $followsOriginal[$index]);
        $follows = self::armsFollow($inlined, $staysArm);
        $arms = [];
        $printed = [];

        foreach ($inlined as $index => $item) {
            if (! $this->mayPrintAsArm($item)) {
                continue;
            }

            $spread = $read[spl_object_id($item)] ?? self::readArm($item, $scope, $engine);
            $arms[$index] = $spread['arm'];

            if ($spread['arm'] === null || ($spread['inPlace'] && ! $follows[$index])) {
                continue;
            }

            // A map's arm after another item is left out where a key the fold writes does not fit its value: the item
            // loop joined the keys before it with that value, which the arm's signature would narrow back.
            $printed[$index] = ['arm' => $spread['arm'], 'mapValue' => $index > 0 ? $spread['mapValue'] : null];
        }

        return new LiteralArms($inlined, $follows, $arms, $printed);
    }

    /**
     * Resolve `$var->toArray()` to the name of `$var`, or null when the expression is not that shape, or `$var` is read
     * past the write that ended its parameter binding, which then names no model `$var` holds.
     *
     * `$this->toArray()` is the resource's own method and is handled elsewhere, so it is excluded
     * by name — `$this` parses as a `Variable` too, which would otherwise match incidentally.
     */
    private static function spreadToArrayVarName(Expr $expr, AnalysisScope $scope): ?string
    {
        if (! $expr instanceof MethodCall
            || ! $expr->name instanceof Identifier
            || $expr->name->toString() !== 'toArray'
            || ! $expr->var instanceof Variable
            || ! is_string($expr->var->name)
            || $expr->var->name === 'this'
            || ! $scope->holdsBinding($expr->var)) {
            return null;
        }

        return $expr->var->name;
    }

    /**
     * Resolve `$var->toArray()`, where `$var` holds one model, to that model's FQCN: a closure-bound one, else the one
     * the receiver resolver names, such as a local's, but a `new` model's, which has no attributes to spread.
     *
     * @return class-string<Model>|null
     */
    private static function spreadModelToArrayFqcn(Expr $expr, AnalysisScope $scope): ?string
    {
        $varName = self::spreadToArrayVarName($expr, $scope);

        if ($varName === null || ! $expr instanceof MethodCall || ! $expr->var instanceof Variable) {
            return null;
        }

        $receivers = resolve(ReceiverClassResolver::class);
        $bound = $receivers->boundModel($expr->var, $scope);

        if ($bound !== null) {
            return $bound;
        }

        // A to-many whenLoaded param holds the whole collection, whose toArray() spreadCollectionToArrayFqcn() reads,
        // and a parameter bound to a value holds that value: neither spreads one model's shape.
        if (isset($scope->varCollectionBindings[$varName]) || isset($scope->varValueBindings[$varName])) {
            return null;
        }

        $held = $receivers->resolve($expr->var, $scope);

        if ($held === null || $held->attributeless || count($held->classes) !== 1 || $held->models() !== $held->classes) {
            return null;
        }

        $model = $held->models()[0];

        return ValueResult::namesOnlyPublishedModels([...ValueResult::unknown(), 'modelFqcn' => $model]) ? $model : null;
    }

    /**
     * Resolve `$var->toArray()`, where `$var` is a closure-bound relation collection, to its
     * element model's FQCN.
     *
     * @return class-string<Model>|null
     */
    private static function spreadCollectionToArrayFqcn(Expr $expr, AnalysisScope $scope): ?string
    {
        return self::spreadToArrayVarName($expr, $scope) !== null && $expr instanceof MethodCall && $expr->var instanceof Variable
            ? resolve(ReceiverClassResolver::class)->boundElementModel($expr->var, $scope)
            : null;
    }

    /**
     * Resolve `$this->relation[->relation...]->toArray()`, where every hop is a real relation on
     * the previous hop's model, to the terminal relation's element FQCN and its to-many-ness.
     *
     * @return array{fqcn: class-string<Model>, isCollection: bool}|null
     */
    private static function spreadRelationChainFqcn(Expr $expr, AnalysisScope $scope): ?array
    {
        if (! $expr instanceof MethodCall
            || ! $expr->name instanceof Identifier
            || $expr->name->toString() !== 'toArray'
            || $expr->isFirstClassCallable()
            || $expr->getArgs() !== []) {
            return null;
        }

        $segments = self::relationChainSegments($expr->var);

        if ($segments === null || $segments === [] || $scope->modelClass === null) {
            return null;
        }

        $resolver = resolve(ModelAttributeResolver::class);
        /** @var class-string<Model> $modelFqcn */
        $modelFqcn = $scope->modelClass;
        $isCollection = false;

        foreach ($segments as $segment) {
            // A hop past an already-to-many relation has no single model left to resolve against
            // (Post::tags() has no further relation to walk) — decline rather than guess.
            if ($isCollection) {
                return null;
            }

            $info = $resolver->resolveRelation($modelFqcn, $segment);

            if ($info['modelFqcn'] === null) {
                return null;
            }

            $modelFqcn = $info['modelFqcn'];
            $isCollection = str_ends_with($info['type'], '[]');
        }

        return ['fqcn' => $modelFqcn, 'isCollection' => $isCollection];
    }

    /**
     * Walk a `$this->a->b->c` property-fetch chain into its segment names, outermost first. Any
     * shape but a chain rooted at `$this` — a computed name, a non-`this` root — declines.
     *
     * @return list<string>|null
     */
    private static function relationChainSegments(Expr $expr): ?array
    {
        $segments = [];

        while ($expr instanceof PropertyFetch) {
            if (! $expr->name instanceof Identifier) {
                return null;
            }

            array_unshift($segments, $expr->name->toString());
            $expr = $expr->var;
        }

        return $expr instanceof Variable && is_string($expr->name) && $expr->name === 'this' ? $segments : null;
    }

    /**
     * Whether a type is one object: an object literal, a `Record`, or a `Pick<>` of a model.
     */
    private static function isObjectType(string $type): bool
    {
        return count(TsTypeString::splitTopLevelUnion($type)) === 1
            && (TsTypeShape::isObjectLike($type) || (str_starts_with($type, 'Pick<') && str_ends_with($type, '>')));
    }

    /**
     * Whether every item of a literal writes a key PHP keeps a string: a spread would renumber an int key, and add keys
     * no name names.
     */
    private static function namesEveryKey(Array_ $array): bool
    {
        return array_all($array->items, static fn (ArrayItem $item): bool => ! $item->unpack && ! $item->byRef
            && $item->key instanceof String_ && PhpArrayKey::storedInt($item->key->value) === null);
    }

    /**
     * How the item loop reads a spread a print may keep as an arm, read once: the arm it classifies as, whether its
     * keys are written in place, and else the value a map it holds may write under any key.
     *
     * @return array{arm: InlineSpreadArm|ObjectSpreadArm|null, inPlace: bool, mapValue: string|null}
     */
    private static function readArm(ArrayItem $item, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $arm = self::classifySpreadArm($item->value, $scope, $engine);
        $inPlace = isset($arm['result']) && self::spreadKeys($item->value, $arm['result'], $scope) !== null;
        $map = isset($arm['result']) && ! $inPlace ? self::spreadSignature($item->value, $arm['result'], $scope) : null;

        return ['arm' => $arm, 'inPlace' => $inPlace, 'mapValue' => $map === null ? null : $map['type']];
    }

    /**
     * Per item, whether a later one stays an arm, as $staysArm says by the item's object id.
     *
     * @param  list<ArrayItem>  $items
     * @param  array<int, bool>  $staysArm
     * @return list<bool>
     */
    private static function armsFollow(array $items, array $staysArm): array
    {
        $follows = [];
        $later = false;

        foreach (array_reverse($items) as $item) {
            $follows[] = $later;
            $later = $later || ($staysArm[spl_object_id($item)] ?? false);
        }

        return array_reverse($follows);
    }

    /**
     * Whether the item loops read a spread's whole value: one readsSpreadByValue() takes, and no resource's, whose arm
     * the print names.
     *
     * @param  ValueExpressionResult  $result
     */
    private static function readsWholeValue(Expr $value, array $result, AnalysisScope $scope): bool
    {
        return ! isset($result['resourceFqcn']) && ! isset($result['resolvedFqcn']) && self::readsSpreadByValue($value, $scope);
    }

    /**
     * The one arm of a spread's value that writes keys, `[]` and `null` aside, which write none; null where the value
     * holds two or none.
     *
     * @param  ValueExpressionResult  $result
     */
    private static function spreadObject(array $result): ?string
    {
        $arms = array_values(array_diff(
            TsTypeString::splitTopLevelUnion(TsTypeString::withoutNullArm($result['type'])),
            ['never[]'],
        ));

        return count($arms) === 1 ? $arms[0] : null;
    }

    /**
     * The value a map type holds under any string key, `undefined` aside: a `Record<string, …>`'s, or a `[key: string]`
     * signature's where that is the object's one member; null for any other type.
     */
    private static function mapValue(string $type): ?string
    {
        $record = TsTypeShape::recordArguments($type);
        $members = $record === null ? TsTypeShape::members($type) : null;
        $value = match (true) {
            $record !== null => $record[0] === 'string' ? $record[1] : null,
            $members !== null && count($members) === 1 => IndexSignatureKey::is($members[0][0])
                && str_ends_with($members[0][0], ': string]') ? $members[0][1] : null,
            default => null,
        };
        $arms = $value === null ? [] : array_diff(TsTypeString::splitTopLevelUnion($value), ['undefined']);

        return $arms === [] ? null : TsTypeString::hoistNull(array_values($arms));
    }

    /**
     * The keys a union of objects writes (D15): per key the arms' values as one union, a key some arm lacks one the
     * spread may leave unset, one an arm's value may remove one it may remove.
     *
     * @param  non-empty-list<KeyedValues>  $arms  each object arm's keys
     * @return KeyedValues
     */
    private static function unionKeys(array $arms): array
    {
        $values = [];

        foreach ($arms as $keys) {
            foreach ($keys as [$name, $result]) {
                $values[$name][] = $result;
            }
        }

        $union = [];

        foreach ($values as $name => $results) {
            $absent = count($results) < count($arms) || array_any($results, static fn (array $result): bool => $result['optional'] && ($result['absent'] ?? false));
            $removed = array_any($results, static fn (array $result): bool => $result['optional'] && (! ($result['absent'] ?? false) || ($result['removed'] ?? false)));
            $joined = ValueResult::unionResults($results);
            unset($joined['absent'], $joined['removed']);

            $union[] = [(string) $name, [
                ...$joined,
                'optional' => $absent || $removed,
                ...($absent ? ['absent' => true] : []),
                ...($absent && $removed ? ['removed' => true] : []),
            ]];
        }

        return $union;
    }

    /**
     * An object's text with its index-signature members left out, whose keys no name names; null where nothing named
     * is left, or the object is no literal object.
     */
    private static function namedObject(string $object): ?string
    {
        $members = TsTypeShape::members($object);

        if ($members === null) {
            return null;
        }

        $named = array_filter($members, static fn (array $member): bool => ! IndexSignatureKey::is($member[0]));

        if ($named === []) {
            return null;
        }

        return count($named) === count($members)
            ? $object
            : '{ '.implode('; ', array_map(static fn (array $member): string => $member[0].': '.$member[1], $named)).' }';
    }

    /**
     * Whether a spread value holds keys no name names: an open literal or signature, or an object its own mark opens.
     *
     * @param  ValueExpressionResult  $result
     */
    private static function holdsUnnamedKeys(array $result): bool
    {
        $object = self::spreadObject($result);

        return ($object !== null && ObjectMembers::isOpen([...$result, 'type' => $object]))
            || ($result['unnamedKeys'] ?? false)
            || in_array("\0".$object, $result['openReadings'] ?? [], true);
    }

    /**
     * Analyze an inline array literal and produce an inline TypeScript object type, or a list type for a list.
     *
     * e.g. `['name' => $this->resource->name, 'value' => $this->maxSizeMb()]`
     * becomes `{ name: string; value: number }`, and `[1, 2]` becomes `number[]`.
     *
     * @return ValueExpressionResult
     */
    private function analyzeInlineArray(Array_ $array, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        // `json_encode([])` emits `[]`, not `{}` — only an array whose keys we failed to resolve is
        // honestly a record. `never[]` says the literal can hold nothing, which is what `[]` means.
        if ($array->items === []) {
            return ['type' => 'never[]', 'optional' => false];
        }

        $merges = $engine->reading() !== Reading::EncodedUnfiltered && $this->holdsMergeCall($array);
        $list = $merges ? null : new ValueResolver()->resolveListLiteral($array, $scope, $engine);

        if ($list !== null) {
            return $list;
        }

        $analysis = $engine->returnArrayAnalysis($array);
        $result = new InlineObjectResult()->of($analysis, $analysis->spreadArms());

        // The object names only the keys written as a string or an int, so array_keys() may find others in it.
        return array_all($array->items, static fn (ArrayItem $item): bool => ! $item->unpack && ($item->key instanceof String_ || $item->key instanceof Int_))
            ? $result
            : [...$result, 'unnamedKeys' => true];
    }

    /**
     * Whether a keyless item is a `$this->merge()`, `mergeWhen()` or `mergeUnless()` call, whose keys an API resource
     * merges into the array instead of listing the call's value; in a response's `with()` or `additional()`, the value
     * is listed.
     */
    private function holdsMergeCall(Array_ $array): bool
    {
        return array_any($array->items, fn (ArrayItem $item): bool => $item->key === null
            && ($this->isThisMethodCall($item->value, 'merge')
                || $this->isThisMethodCall($item->value, 'mergeWhen')
                || $this->isThisMethodCall($item->value, 'mergeUnless')));
    }

    /**
     * Whether an item is a spread a print may keep as an arm: none the item loop flattens itself.
     */
    private function mayPrintAsArm(ArrayItem $item): bool
    {
        return $item->key === null && $item->unpack && ! $this->isKnownArraySpreadShape($item->value);
    }

    /**
     * Whether a spread's value matches one of the four shapes ExpressionEngine::returnArrayAnalysis()'s
     * item loop already flattens into named properties (parent::toArray(), ->only()/->except(), a bare
     * `$this->method()`, or a bare function call) — already handled, so not a resource candidate.
     */
    private function isKnownArraySpreadShape(Expr $value): bool
    {
        if ($this->isParentCallTo($value)) {
            return true;
        }

        if ($value instanceof MethodCall && $value->var instanceof Variable && $value->var->name === 'this') {
            return true;
        }

        return $value instanceof FuncCall;
    }
}
