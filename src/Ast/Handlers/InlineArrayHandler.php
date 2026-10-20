<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\Composition\CastChannels;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\ClassTokenQueue;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * An inline array literal: `['name' => $this->resource->name, 'value' => $this->maxSizeMb()]` becomes
 * `{ name: string; value: number }`, a list such as `[$a, $b]` a list type. Spreads of a bare named resource, a bound
 * model's or collection's toArray(), or any other value typed as one object fold into intersection arms.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type EnumResourceArmShape from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type InlineModelFqcnsMap from MethodAnalysis
 * @phpstan-import-type InlineResourceFqcnsMap from MethodAnalysis
 *
 * @phpstan-type InlineSpreadArm = array{fqcn: class-string, isModel: bool, isCollection: bool}
 * @phpstan-type ObjectSpreadArm = array{type: string, result: ValueExpressionResult}
 * @phpstan-type SpreadArmFqcns = array{
 *      models: list<class-string>,
 *      resources: list<class-string>,
 *      enums: list<class-string>,
 *      enumResources: list<class-string>,
 * }
 * @phpstan-type MemberNames = array<string, list<string>>
 *
 * @internal
 */
final class InlineArrayHandler implements ExpressionHandler
{
    use BuildsInlineObjectTypes;
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
        // import behind it.
        $type = ValueResult::stripNullArm($spreadResult['type']);

        return self::isObjectType($type) && ValueResult::namesOnlyExportedClasses($spreadResult)
            ? ['type' => $type, 'result' => $spreadResult]
            : null;
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
     * An analysis as the inline object type it publishes, carrying the import channels of every class it names.
     *
     * A JSON:API resource object publishes its `attributes` analysis through this, whatever source built it.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms  the arms the object type intersects with
     * @return ValueExpressionResult
     */
    public function inlineObjectResult(MethodAnalysis $analysis, array $spreadArms = []): array
    {
        // No outer cast can retype a member, so a member's own method-level cast is the one in force.
        resolve(CastChannels::class)->fit($analysis, $analysis->casts);

        if ($analysis->properties === [] && $spreadArms === []) {
            return ['type' => 'Record<string, unknown>', 'optional' => false];
        }

        $useTolki = Config::boolean('ts-publish.enums.use_tolki_package');

        // Tolki on: EnumResource-wrapped properties render as `AsEnum<typeof X>`, matching the
        // top-level enum resource transformer. Substituting the bare token in place keeps every
        // other union arm — a keyed `Record<...>` arm, an extra default arm — intact.
        if ($useTolki) {
            foreach ($analysis->properties as &$prop) {
                if (! isset($analysis->enumResources[$prop['name']])) {
                    continue;
                }

                $fqcn = $analysis->enumResources[$prop['name']];
                $tsInfo = LaravelTsPublish::toTsType($fqcn);
                $constName = $tsInfo['enums'][0] ?? class_basename($fqcn);
                $bareTypeName = $tsInfo['enumTypes'][0] ?? class_basename($fqcn).'Type';
                $asEnumType = 'AsEnum<typeof '.$constName.'>';

                // A mixed wrap/direct ternary needs both arms named, whether or not the merged union
                // still shows them apart; blanket substitution would rewrite the direct arm too.
                $isMixed = ($analysis->directEnumFqcns[$prop['name']] ?? null) === $fqcn;
                $members = TsTypeString::splitTopLevelUnion($prop['type']);

                $prop['type'] = $isMixed
                    ? $this->expandMixedEnumType(
                        $members,
                        $bareTypeName,
                        $asEnumType,
                        $analysis->enumResourceArmShapes[$prop['name']] ?? null,
                    )
                    : TsTypeString::substituteEnumType($prop['type'], $bareTypeName, $asEnumType);
            }

            unset($prop);
        }

        // Each spread resource intersects with the remaining explicit keys, minus whichever of its
        // own keys a later spread arm or an explicit key also sets — PHP's `[...a, ...b, 'k' => v]`
        // lets the later assignment win, `&` does not, so the earlier arm needs Omit<>'d.
        $spreadArmTypes = array_values(array_unique(
            $this->buildSpreadArmTypes($spreadArms, array_column($analysis->properties, 'name')),
        ));
        $type = match (true) {
            $spreadArms === [] => $this->buildInlineObjectType($analysis),
            $analysis->properties === [] => implode(' & ', $spreadArmTypes),
            default => implode(' & ', [...$spreadArmTypes, $this->buildInlineObjectType($analysis)]),
        };

        $result = ['type' => $type, 'optional' => false];

        if ($spreadArms === [] && $this->keepsMemberKeys($analysis)) {
            $result['keepsKeys'] = true;
        }

        // Propagate import metadata so FQCNs referenced inside the inline object reach the outer analysis.
        // With Tolki enabled, enum resources need value imports (const) rather than type imports; direct
        // enum accesses always need type imports.
        if ($useTolki) {
            // Never deduped: aliasPropertyType() walks this list positionally against left-to-right
            // occurrences of each bare enum name in the rendered type, so a real repeat must survive.
            $embeddedEnumFqcns = $this->memberEnumFqcns($analysis, false);

            $enumResourceFqcns = array_values($analysis->enumResources);
            // Propagate any deeply-nested enum resource FQCNs from sub-inline-arrays.
            foreach ($analysis->inlineEnumResourceFqcns as $nestedFqcns) {
                foreach ($nestedFqcns as $fqcn) {
                    $enumResourceFqcns[] = $fqcn;
                }
            }
            // Never deduped: same positional reasoning as $embeddedEnumFqcns above, for the
            // EnumResource-wrapped (value-import) channel.
            $embeddedEnumResourceFqcns = $enumResourceFqcns;
        } else {
            // Tolki OFF: all enum FQCNs (both direct and EnumResource) need type imports. Never
            // deduped, same positional reasoning as the Tolki-on branch above.
            $embeddedEnumFqcns = $this->memberEnumFqcns($analysis, true);
            $embeddedEnumResourceFqcns = [];
        }

        // Spread arms lead the type, so their tokens lead each queue.
        $armFqcns = $this->spreadArmFqcns($spreadArms);
        $embeddedEnumFqcns = [...$armFqcns['enums'], ...$embeddedEnumFqcns];
        $embeddedEnumResourceFqcns = [...$armFqcns['enumResources'], ...$embeddedEnumResourceFqcns];

        $resourceNameOf = static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn);
        $sharedNames = $this->sharedNames($analysis, $resourceNameOf);

        // Spread arms lead the type, then each key once, at its first position, with every occurrence kept: the
        // self-keyed $analysis->modelFqcns map collapses repeated FQCNs onto one key, dropping a multi-FQCN accessor
        // member's own arms.
        $embeddedModelFqcns = [
            ...$armFqcns['models'],
            ...$this->memberFqcns(
                $analysis,
                $analysis->inlineModelFqcns,
                $analysis->modelFqcns,
                class_basename(...),
                $sharedNames,
            ),
        ];

        // An enum whose bare name no longer occurs in the final type (its arm was substituted by a
        // wrapped one) must not claim an import the transformer would then emit unused.
        $embeddedEnumFqcns = array_values(array_filter(
            $embeddedEnumFqcns,
            fn (string $fqcn): bool => TsTypeString::typeNameOccursIn(
                LaravelTsPublish::toTsType($fqcn)['type'],
                $result['type'],
            ),
        ));

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        if ($embeddedEnumResourceFqcns !== []) {
            $result['embeddedEnumResourceFqcns'] = $embeddedEnumResourceFqcns;
        }

        if ($embeddedModelFqcns !== []) {
            $result['embeddedModelFqcns'] = $embeddedModelFqcns;
        }

        // Nested resources are tracked separately so they merge into resource imports, not model imports. One entry
        // per resource token, in the order the type spells them: spread arms lead the type, then each member, and a
        // member whose own union names two resources carries both. Never deduped, as for the models above.
        $embeddedResourceFqcns = [
            ...$armFqcns['resources'],
            ...$this->memberFqcns(
                $analysis,
                $analysis->inlineResourceFqcns,
                $analysis->nestedResources,
                $resourceNameOf,
                $sharedNames,
            ),
        ];

        if ($embeddedResourceFqcns !== []) {
            $result['embeddedResourceFqcns'] = $embeddedResourceFqcns;
        }

        foreach ($spreadArms as $arm) {
            if (isset($arm['result'])) {
                $analysis->carry($arm['result']['carriedFqcns'] ?? []);

                foreach ($arm['result']['customImports'] ?? [] as $path => $types) {
                    $analysis->customImports[$path] = [...($analysis->customImports[$path] ?? []), ...$types];
                }
            }
        }

        // A #[TsType(import: …)] token inside the inline object is spelled in the emitted type string,
        // so its import has to travel out with it.
        if ($analysis->customImports !== []) {
            $result['customImports'] = $analysis->customImports;
        }

        // A member's carried class travels on its own, never queued: only a text with no class of its name keeps it.
        if ($analysis->carried !== []) {
            $result['carriedFqcns'] = $analysis->carried;
        }

        return $result;
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
     * Resolve `$var->toArray()`, where `$var` is a closure-bound model, to that model's FQCN.
     *
     * @return class-string<Model>|null
     */
    private static function spreadModelToArrayFqcn(Expr $expr, AnalysisScope $scope): ?string
    {
        $varName = self::spreadToArrayVarName($expr, $scope);

        if ($varName === null) {
            return null;
        }

        if (isset($scope->varModelBindings[$varName])) {
            return $scope->varModelBindings[$varName];
        }

        // A to-many whenLoaded param holds the whole collection, not one element — its toArray()
        // is a list of member arrays, never a single model's shape. spreadCollectionToArrayFqcn()
        // picks it up instead.
        if (isset($scope->varCollectionBindings[$varName])) {
            return null;
        }

        return $scope->closureRelationModelClass;
    }

    /**
     * Resolve `$var->toArray()`, where `$var` is a closure-bound relation collection, to its
     * element model's FQCN.
     *
     * @return class-string<Model>|null
     */
    private static function spreadCollectionToArrayFqcn(Expr $expr, AnalysisScope $scope): ?string
    {
        $varName = self::spreadToArrayVarName($expr, $scope);

        return $varName === null ? null : ($scope->varCollectionBindings[$varName]['modelFqcn'] ?? null);
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
     * A result's classes of one kind: its single-entry import channel's class, then its embedded queue.
     *
     * @param  class-string|null  $single
     * @param  list<class-string>  $embedded
     * @return list<class-string>
     */
    private static function channelFqcns(?string $single, array $embedded): array
    {
        return [...($single !== null ? [$single] : []), ...$embedded];
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

        $list = $this->holdsMergeCall($array) ? null : new ValueResolver()->resolveListLiteral($array, $scope, $engine);

        if ($list !== null) {
            return $list;
        }

        $analysis = $engine->returnArrayAnalysis($array);

        // A spread whose value resolves to a bare named resource (not an array/collection of one), to a
        // bound model's toArray(), or to a bound collection's toArray(), intersects with the literal's keys.
        return $this->inlineObjectResult($analysis, $this->collectInlineArraySpreadArms($array, $scope, $engine));
    }

    /**
     * The class behind each token of the members' types, in the order the inline object type spells them.
     *
     * That type keeps a key declared twice at its first position with its last value, so each key is walked once: a
     * second row for it would queue the same classes again and push every later token onto the wrong alias.
     *
     * @param  InlineModelFqcnsMap|InlineResourceFqcnsMap  $queues  member name => the class behind each of its tokens
     * @param  ClassMapType  $singles  member name => the one class its type names, for a member without a queue
     * @param  Closure(class-string): string  $nameOf  the name a class's token is spelled with
     * @param  MemberNames  $sharedNames  member name => the names it queues on both channels
     * @return list<class-string>
     */
    private function memberFqcns(
        MethodAnalysis $analysis,
        array $queues,
        array $singles,
        Closure $nameOf,
        array $sharedNames,
    ): array {
        $fqcns = [];
        $types = array_column($analysis->properties, 'type', 'name');

        foreach (array_unique(array_column($analysis->properties, 'name')) as $memberName) {
            $queue = $this->memberQueue($memberName, $queues, $singles);
            $shared = $sharedNames[$memberName] ?? [];

            array_push($fqcns, ...ClassTokenQueue::perToken($queue, $types[$memberName], $nameOf, $shared));
        }

        return $fqcns;
    }

    /**
     * The enum behind each enum token of the members' types, member by member in the order the inline object type
     * spells them, then each enum keyed by its own FQCN, which no member owns.
     *
     * A member whose cast moved its enum to an inline queue keeps its place, as aliasing reads each name's queue in
     * order; with `$withWraps`, a member's `EnumResource` enums join its own, for type imports.
     *
     * @return list<class-string>
     */
    private function memberEnumFqcns(MethodAnalysis $analysis, bool $withWraps): array
    {
        $fqcns = [];
        $memberNames = array_unique(array_column($analysis->properties, 'name'));

        foreach ($memberNames as $memberName) {
            $direct = $analysis->directEnumFqcns[$memberName] ?? null;
            $wrapped = $withWraps ? ($analysis->enumResources[$memberName] ?? null) : null;

            array_push(
                $fqcns,
                ...($direct !== null ? [$direct] : []),
                ...($wrapped !== null ? [$wrapped] : []),
                ...($analysis->inlineEnumFqcns[$memberName] ?? []),
                ...($withWraps ? ($analysis->inlineEnumResourceFqcns[$memberName] ?? []) : []),
            );
        }

        $members = array_flip($memberNames);

        foreach ($analysis->directEnumFqcns as $key => $fqcn) {
            if (! isset($members[$key])) {
                $fqcns[] = $fqcn;
            }
        }

        return $fqcns;
    }

    /**
     * The names each member queues on both channels, a class on each: a model and a resource under one name.
     *
     * Both queues are merged for aliasing, models first, so a name queued on both is left as it came on both.
     *
     * @param  Closure(class-string): string  $resourceNameOf
     * @return MemberNames
     */
    private function sharedNames(MethodAnalysis $analysis, Closure $resourceNameOf): array
    {
        $shared = [];

        foreach (array_unique(array_column($analysis->properties, 'name')) as $memberName) {
            $models = $this->memberQueue($memberName, $analysis->inlineModelFqcns, $analysis->modelFqcns);
            $resources = $this->memberQueue($memberName, $analysis->inlineResourceFqcns, $analysis->nestedResources);

            $shared[$memberName] = array_values(array_intersect(
                array_map(class_basename(...), $models),
                array_map($resourceNameOf, $resources),
            ));
        }

        return $shared;
    }

    /**
     * The classes a member's own analysis queued on one channel: its queue, else the one class its type names.
     *
     * @param  InlineModelFqcnsMap|InlineResourceFqcnsMap  $queues
     * @param  ClassMapType  $singles
     * @return list<class-string>
     */
    private function memberQueue(string $memberName, array $queues, array $singles): array
    {
        return $queues[$memberName] ?? (isset($singles[$memberName]) ? [$singles[$memberName]] : []);
    }

    /**
     * Whether an API resource's re-index leaves the literal's object as it is: a key that is not a number keeps every
     * key, and each member holding an object keeps its own.
     */
    private function keepsMemberKeys(MethodAnalysis $analysis): bool
    {
        // Keyed by name, a later member replacing an earlier one, as buildInlineObjectType() prints them.
        $members = array_column($analysis->properties, null, 'name');

        return ! array_all(array_keys($members), fn (int|string $name): bool => TsTypeShape::isNumericKey((string) $name))
            && array_all($members, fn (array $member): bool => ($member['keepsKeys'] ?? false)
                || ! TsTypeShape::holdsKeyedObject($member['type']));
    }

    /**
     * Whether a keyless item is a `$this->merge()`, `mergeWhen()` or `mergeUnless()` call, whose keys an API resource
     * merges into the array instead of listing the call's value.
     */
    private function holdsMergeCall(Array_ $array): bool
    {
        return array_any($array->items, fn (ArrayItem $item): bool => $item->key === null
            && ($this->isThisMethodCall($item->value, 'merge')
                || $this->isThisMethodCall($item->value, 'mergeWhen')
                || $this->isThisMethodCall($item->value, 'mergeUnless')));
    }

    /**
     * Collect every spread in an inline array that classifySpreadArm() takes, in source order — the
     * arms an intersection type is built from.
     *
     * @return list<InlineSpreadArm|ObjectSpreadArm>
     */
    private function collectInlineArraySpreadArms(Array_ $array, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        /** @var list<InlineSpreadArm|ObjectSpreadArm> $spreadArms */
        $spreadArms = [];

        foreach ($array->items as $item) {
            if ($item->key !== null || ! $item->unpack || $this->isKnownArraySpreadShape($item->value)) {
                continue;
            }

            $arm = self::classifySpreadArm($item->value, $scope, $engine);

            if ($arm !== null) {
                $spreadArms[] = $arm;
            }
        }

        return $spreadArms;
    }

    /**
     * The classes the spread arms' tokens name, per queue, in arm order.
     *
     * A named arm queues its class on the import channel of its kind, or the emitted `Model &` token would be looked up
     * among the resources and never resolve to an import; an object arm queues what its own value carries.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms
     * @return SpreadArmFqcns
     */
    private function spreadArmFqcns(array $spreadArms): array
    {
        $fqcns = ['models' => [], 'resources' => [], 'enums' => [], 'enumResources' => []];

        foreach ($spreadArms as $arm) {
            if (! isset($arm['result'])) {
                $fqcns[$arm['isModel'] ? 'models' : 'resources'][] = $arm['fqcn'];

                continue;
            }

            $result = $arm['result'];

            array_push($fqcns['models'], ...self::channelFqcns($result['modelFqcn'] ?? null, $result['embeddedModelFqcns'] ?? []));
            array_push($fqcns['resources'], ...self::channelFqcns($result['resourceFqcn'] ?? null, $result['embeddedResourceFqcns'] ?? []));
            array_push($fqcns['enums'], ...self::channelFqcns($result['directEnumFqcn'] ?? null, $result['embeddedEnumFqcns'] ?? []));
            array_push($fqcns['enumResources'], ...($result['embeddedEnumResourceFqcns'] ?? []));
        }

        return $fqcns;
    }

    /**
     * Build each spread's intersection arm, `Omit<>`'d against every key a later arm or an
     * explicit key will overwrite at runtime. `Omit<T, K>` doesn't require `K extends keyof T`,
     * so a later arm's own shape never has to be resolved — only its name, for `keyof`.
     *
     * @param  list<InlineSpreadArm|ObjectSpreadArm>  $spreadArms
     * @param  list<string>  $explicitKeyNames
     * @return list<string>
     */
    private function buildSpreadArmTypes(array $spreadArms, array $explicitKeyNames): array
    {
        $explicitKeyLiterals = array_map(fn (string $key): string => "'{$key}'", $explicitKeyNames);

        return array_map(function (int $index) use ($spreadArms, $explicitKeyLiterals): string {
            $armName = $this->spreadArmName($spreadArms[$index]);

            // Spreading a collection renumbers its elements 0..n, so a collection arm holds only
            // numeric keys: nothing string-keyed can overwrite it, and it overwrites nothing.
            if ($spreadArms[$index]['isCollection'] ?? false) {
                return "Record<number, {$armName}>";
            }

            $laterArmNames = array_values(array_unique(array_map(
                $this->spreadArmName(...),
                array_filter(array_slice($spreadArms, $index + 1), fn (array $arm): bool => ! ($arm['isCollection'] ?? false)),
            )));

            $excluded = [
                ...$explicitKeyLiterals,
                ...array_map(fn (string $name): string => "keyof {$name}", $laterArmNames),
            ];

            return $excluded === [] ? $armName : 'Omit<'.$armName.', '.implode(' | ', $excluded).'>';
        }, array_keys($spreadArms));
    }

    /**
     * The type a spread arm is spelled with: a named arm's class, or an object arm's own type.
     *
     * @param  InlineSpreadArm|ObjectSpreadArm  $arm
     */
    private function spreadArmName(array $arm): string
    {
        return isset($arm['type']) ? $arm['type'] : TsNaming::resourceTypeName($arm['fqcn']);
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

    /**
     * Rejoin a mixed wrap/direct enum union's split members, naming the wrapped arm without
     * losing the direct one.
     *
     * With each arm's own shape recorded, the union is synthesised from those flags — the merged
     * members alone cannot tell two arms that rendered the same string apart. Without it, an
     * array-shaped member is taken as the arm EnumResource::collection() forced.
     *
     * @param  list<string>  $members
     * @param  EnumResourceArmShape|null  $armShape
     */
    private function expandMixedEnumType(array $members, string $bareTypeName, string $asEnumType, ?array $armShape): string
    {
        if ($armShape !== null) {
            $wrapped = $asEnumType.($armShape['wrapIsCollection'] ? '[]' : '');
            $direct = $bareTypeName.($armShape['directIsArray'] ? '[]' : '');
            $others = array_values(array_filter(
                $members,
                fn (string $member): bool => $member !== $bareTypeName && $member !== $bareTypeName.'[]',
            ));

            return implode(' | ', [$wrapped, $direct, ...$others]);
        }

        $collectionType = $bareTypeName.'[]';
        $hasCollectionArm = in_array($collectionType, $members, true);

        $expanded = array_map(fn (string $member): string => match (true) {
            $member === $collectionType => $asEnumType.'[]',
            ! $hasCollectionArm && $member === $bareTypeName => $asEnumType.' | '.$bareTypeName,
            default => $member,
        }, $members);

        return implode(' | ', $expanded);
    }
}
