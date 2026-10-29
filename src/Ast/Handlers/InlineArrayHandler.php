<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\InlineObjectResult;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Model;
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
 * @phpstan-import-type InlineSpreadArm from InlineObjectResult
 * @phpstan-import-type ObjectSpreadArm from InlineObjectResult
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
        return new InlineObjectResult()->of($analysis, $this->collectInlineArraySpreadArms($array, $scope, $engine));
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
