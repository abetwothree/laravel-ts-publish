<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Closure;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * An inline array literal, e.g. `['name' => $this->resource->name, 'value' => $this->maxSizeMb()]`
 * becomes `{ name: string; value: number }`. Also folds spreads that resolve to a bare named
 * resource, a bound model's toArray(), or a bound collection's toArray() into intersection arms.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type EnumResourceArmShape from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type InlineModelFqcnsMap from MethodAnalysis
 * @phpstan-import-type InlineResourceFqcnsMap from MethodAnalysis
 *
 * @phpstan-type InlineSpreadArm = array{fqcn: class-string, isModel: bool, isCollection: bool}
 * @phpstan-type MemberNames = array<string, list<string>>
 *
 * @internal
 */
final class InlineArrayHandler implements ExpressionHandler
{
    use BuildsInlineObjectTypes;
    use InspectsAstNodes;

    /**
     * Classify a spread's value expression as a bare named resource, a bound model's toArray(), or
     * a bound collection's toArray() — the three shapes an inline array's intersection is built
     * from. Shared with ResourceAstAnalyzer's top-level flatten path so the two never diverge on
     * the same shape.
     *
     * @return InlineSpreadArm|null
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

        return null;
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
     * Resolve `$var->toArray()` to the name of `$var`, or null when the expression is not that shape.
     *
     * `$this->toArray()` is the resource's own method and is handled elsewhere, so it is excluded
     * by name — `$this` parses as a `Variable` too, which would otherwise match incidentally.
     */
    private static function spreadToArrayVarName(Expr $expr): ?string
    {
        if (! $expr instanceof MethodCall
            || ! $expr->name instanceof Identifier
            || $expr->name->toString() !== 'toArray'
            || ! $expr->var instanceof Variable
            || ! is_string($expr->var->name)
            || $expr->var->name === 'this') {
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
        $varName = self::spreadToArrayVarName($expr);

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
        $varName = self::spreadToArrayVarName($expr);

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
     * Analyze an inline array literal and produce an inline TypeScript object type.
     *
     * e.g. `['name' => $this->resource->name, 'value' => $this->maxSizeMb()]`
     * becomes `{ name: string; value: number }`.
     *
     * @return ValueExpressionResult
     */
    private function analyzeInlineArray(Array_ $array, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $analysis = $engine->returnArrayAnalysis($array);

        // `json_encode([])` emits `[]`, not `{}` — only an array whose keys we failed to resolve is
        // honestly a record. `never[]` says the literal can hold nothing, which is what `[]` means.
        if ($array->items === []) {
            return ['type' => 'never[]', 'optional' => false];
        }

        // A spread whose value resolves to a bare named resource (not an array/collection of one), to a
        // bound model's toArray(), or to a bound collection's toArray(), intersects with the literal's keys.
        $spreadArms = $this->collectInlineArraySpreadArms($array, $scope, $engine);

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

        // Propagate import metadata so FQCNs referenced inside the inline object reach the outer analysis.
        // With Tolki enabled, enum resources need value imports (const) rather than type imports; direct
        // enum accesses always need type imports.
        if ($useTolki) {
            $nestedInlineEnumFqcns = $analysis->inlineEnumFqcns === []
                 ? []
                 : array_merge(...array_values($analysis->inlineEnumFqcns));

            // Never deduped: aliasPropertyType() walks this list positionally against left-to-right
            // occurrences of each bare enum name in the rendered type, so a real repeat must survive.
            $embeddedEnumFqcns = [
                ...array_values($analysis->directEnumFqcns),
                // Propagate any deeply-nested direct enum FQCNs from sub-inline-arrays.
                ...$nestedInlineEnumFqcns,
            ];

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
            $embeddedEnumFqcns = [
                ...array_values($analysis->directEnumFqcns),
                ...array_values($analysis->enumResources),
                ...array_merge(...array_values($analysis->inlineEnumFqcns)),
                ...array_merge(...array_values($analysis->inlineEnumResourceFqcns)),
            ];
            $embeddedEnumResourceFqcns = [];
        }

        // Each spread arm's import travels the channel matching its kind, or the emitted `Model &`
        // token would be looked up among the resources and never resolve to an import.
        $spreadModelFqcns = array_column(array_filter($spreadArms, fn (array $arm): bool => $arm['isModel']), 'fqcn');
        $spreadResourceFqcns = array_column(array_filter($spreadArms, fn (array $arm): bool => ! $arm['isModel']), 'fqcn');

        $resourceNameOf = static fn (string $fqcn): string => TsNaming::resourceTypeName($fqcn);
        $sharedNames = $this->sharedNames($analysis, $resourceNameOf);

        // Spread arms lead the type, then each key once, at its first position, with every occurrence kept: the
        // self-keyed $analysis->modelFqcns map collapses repeated FQCNs onto one key, dropping a multi-FQCN accessor
        // member's own arms.
        $embeddedModelFqcns = [
            ...$spreadModelFqcns,
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
            ...$spreadResourceFqcns,
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

        // A #[TsType(import: …)] token inside the inline object is spelled in the emitted type string,
        // so its import has to travel out with it.
        if ($analysis->customImports !== []) {
            $result['customImports'] = $analysis->customImports;
        }

        return $result;
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

            array_push($fqcns, ...$this->perToken($queue, $types[$memberName], $nameOf, $shared));
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
     * One member's queue with one entry per token for each name it gives a single class.
     *
     * A merge by text can queue that class more or less often than the member spells it, and the next member's tokens
     * would read the difference. A name the member queues on both channels, one class on each, is left as it came.
     *
     * @param  list<class-string>  $queue
     * @param  Closure(class-string): string  $nameOf
     * @param  list<string>  $sharedNames  the names the member queues on both channels
     * @return list<class-string>
     */
    private function perToken(array $queue, string $type, Closure $nameOf, array $sharedNames): array
    {
        /** @var array<string, list<class-string>> $classesOf name => the classes queued for it, in order */
        $classesOf = [];

        foreach ($queue as $fqcn) {
            $classesOf[$nameOf($fqcn)][] = $fqcn;
        }

        /** @var array<string, int> $room name => the entries it still gets, for a name that has one class behind it */
        $room = [];

        foreach ($classesOf as $name => $classes) {
            if (count(array_unique($classes)) === 1 && ! in_array($name, $sharedNames, true)) {
                // At least one: this rule leaves an entry for a class the member's type does not spell as it came.
                $room[$name] = max(1, (int) preg_match_all(TsTypeString::queuedTokenPattern([$name]), $type));
            }
        }

        $perToken = [];

        foreach ($queue as $fqcn) {
            $name = $nameOf($fqcn);

            if (! isset($room[$name])) {
                $perToken[] = $fqcn;
            } elseif ($room[$name] > 0) {
                $perToken[] = $fqcn;
                $room[$name]--;
            }
        }

        foreach ($room as $name => $left) {
            for ($i = 0; $i < $left; $i++) {
                $perToken[] = $classesOf[$name][0];
            }
        }

        return $perToken;
    }

    /**
     * Collect every spread in an inline array resolving to a bare named resource, to a bound
     * model's toArray(), or to a bound collection's toArray(), in source order — the arms an
     * intersection type is built from.
     *
     * @return list<InlineSpreadArm>
     */
    private function collectInlineArraySpreadArms(Array_ $array, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        /** @var list<InlineSpreadArm> $spreadArms */
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
     * Build each spread's intersection arm, `Omit<>`'d against every key a later arm or an
     * explicit key will overwrite at runtime. `Omit<T, K>` doesn't require `K extends keyof T`,
     * so a later arm's own shape never has to be resolved — only its name, for `keyof`.
     *
     * @param  list<InlineSpreadArm>  $spreadArms
     * @param  list<string>  $explicitKeyNames
     * @return list<string>
     */
    private function buildSpreadArmTypes(array $spreadArms, array $explicitKeyNames): array
    {
        $explicitKeyLiterals = array_map(fn (string $key): string => "'{$key}'", $explicitKeyNames);

        return array_map(function (int $index) use ($spreadArms, $explicitKeyLiterals): string {
            $armName = TsNaming::resourceTypeName($spreadArms[$index]['fqcn']);

            // Spreading a collection renumbers its elements 0..n, so a collection arm holds only
            // numeric keys: nothing string-keyed can overwrite it, and it overwrites nothing.
            if ($spreadArms[$index]['isCollection']) {
                return "Record<number, {$armName}>";
            }

            $laterArmNames = array_values(array_unique(array_map(
                fn (array $arm): string => TsNaming::resourceTypeName($arm['fqcn']),
                array_filter(array_slice($spreadArms, $index + 1), fn (array $arm): bool => ! $arm['isCollection']),
            )));

            $excluded = [
                ...$explicitKeyLiterals,
                ...array_map(fn (string $name): string => "keyof {$name}", $laterArmNames),
            ];

            return $excluded === [] ? $armName : 'Omit<'.$armName.', '.implode(' | ', $excluded).'>';
        }, array_keys($spreadArms));
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
