<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\FiltersModelAttributes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ResolvesModelTypes;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
use PhpParser\Node\Stmt\Expression as ExpressionStmt;
use PhpParser\Node\Stmt\For_;
use PhpParser\Node\Stmt\Foreach_;
use PhpParser\Node\Stmt\If_;
use PhpParser\Node\Stmt\Return_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use ReflectionClass;
use ReflectionEnum;

/**
 * Analyzes a JsonResource's toArray() method body to extract property names,
 * types, and conditional (optional) markers using AST parsing.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 * @phpstan-import-type ImportMapType from ResourceAnalysis
 *
 * @phpstan-type ValueExpressionResult = array{
 *      type: string,
 *      optional: bool,
 *      enumFqcn?: class-string,
 *      directEnumFqcn?: class-string,
 *      resourceFqcn?: class-string,
 *      modelFqcn?: class-string,
 *      embeddedEnumFqcns?: list<class-string>,
 *      embeddedModelFqcns?: list<class-string>,
 *      embeddedResourceFqcns?: list<class-string>
 * }
 */
class ResourceAstAnalyzer
{
    use FiltersModelAttributes;
    use InspectsAstNodes;
    use ResolvesClassNames;
    use ResolvesModelTypes;

    /**
     * Wrapped class discovered from an `instanceof` guard clause in toArray().
     * Used as a fallback when resolveClassOnProperty() returns null.
     *
     * @var class-string|null
     */
    protected ?string $instanceOfWrappedClass = null;

    /**
     * Related model class temporarily set when analyzing a whenLoaded closure.
     * Enables type resolution for `$variable->property`, `$variable->method()`,
     * and `$variable::staticMethod()` expressions within the closure body.
     *
     * @var class-string<Model>|null
     */
    protected ?string $closureRelationModelClass = null;

    /**
     * @param  ReflectionClass<JsonResource>  $resourceReflection
     * @param  class-string<Model>|null  $modelClass
     */
    public function __construct(
        protected ReflectionClass $resourceReflection,
        protected ?string $modelClass = null,
    ) {
        if ($this->modelClass !== null) {
            $this->loadModelInspectorData();
        }
    }

    public function analyze(): ResourceAnalysis
    {
        $filePath = (string) $this->resourceReflection->getFileName();
        $source = (string) file_get_contents($filePath);

        $stmts = $this->parseAndResolveAst($source);

        $finder = new NodeFinder;
        $toArrayMethod = $finder->findFirst($stmts, function (Node $node): bool {
            return $node instanceof ClassMethod && $node->name->toString() === 'toArray';
        });

        if (! $toArrayMethod instanceof ClassMethod || $toArrayMethod->stmts === null) {
            return $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
        }

        // Extract instanceof type hint from guard clauses before selecting the return statement.
        // e.g. `if (!$this->resource instanceof MediaType) { return []; }`
        $this->instanceOfWrappedClass = $this->resolveInstanceOfType($toArrayMethod, $finder);

        // Analyze all direct Return_ statements that yield non-empty array literals.
        // If multiple exist (e.g. if/elseif/else branches), their properties are merged
        // with union semantics (branch-specific properties become optional).
        $branchAnalysis = $this->analyzeAllReturnBranches($toArrayMethod->stmts);

        if ($branchAnalysis !== null) {
            return $branchAnalysis;
        }

        // Fallback: find the first Return_ for non-array returns (parent::toArray, $this->only, etc.)
        $returnStmt = $finder->findFirst($toArrayMethod->stmts, function (Node $node): bool {
            return $node instanceof Return_;
        });

        if (! $returnStmt instanceof Return_ || $returnStmt->expr === null) {
            return new ResourceAnalysis; // @codeCoverageIgnore
        }

        if ($this->isParentToArrayCall($returnStmt->expr)) {
            return $this->analyzeParentToArray() ?? $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
        }

        // return $this->only([...]) or return $this->except([...])
        if ($returnStmt->expr instanceof MethodCall) {
            return $this->analyzeThisAttributeFilter($returnStmt->expr) ?? new ResourceAnalysis;
        }

        return new ResourceAnalysis;
    }

    protected function analyzeReturnArray(Array_ $array): ResourceAnalysis
    {
        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $enumResources */
        $enumResources = [];
        /** @var ClassMapType $nestedResources */
        $nestedResources = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];
        /** @var ImportMapType $customImports */
        $customImports = [];

        foreach ($array->items as $item) {
            // Handle ...parent::toArray($request) spread
            if ($item->key === null && $item->unpack && $this->isParentToArrayCall($item->value)) {
                $parentAnalysis = $this->analyzeParentToArray();

                if ($parentAnalysis !== null) {
                    $this->syncAnalysisMaps(
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports,
                        $parentAnalysis,
                    );
                }

                continue;
            }

            // Handle ...$this->only([...]) or ...$this->except([...]) spread
            if ($item->key === null && $item->unpack
                && $item->value instanceof MethodCall
                && $item->value->var instanceof Variable
                && $item->value->var->name === 'this'
                && $item->value->name instanceof Identifier
                && in_array($item->value->name->toString(), $this->supportedAttributeFilters(), true)) {
                $filterAnalysis = $this->analyzeThisAttributeFilter($item->value);

                if ($filterAnalysis !== null) {
                    $this->syncAnalysisMaps(
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports,
                        $filterAnalysis,
                    );
                }

                continue;
            }

            // Handle ...$this->method() spread (e.g., trait methods returning arrays)
            if ($item->key === null && $item->unpack
                && $item->value instanceof MethodCall
                && $item->value->var instanceof Variable
                && $item->value->var->name === 'this'
                && $item->value->name instanceof Identifier) {
                $spreadAnalysis = $this->analyzeThisMethodSpread($item->value->name->toString());

                if ($spreadAnalysis !== null) {
                    $this->syncAnalysisMaps(
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports,
                        $spreadAnalysis,
                    );
                }

                continue;
            }

            // Handle ...functionCall() spread (bare trait method calls without $this->)
            if ($item->key === null && $item->unpack
                && $item->value instanceof FuncCall
                && $item->value->name instanceof Name) {
                $funcName = $item->value->name->getLast();

                if ($this->resourceReflection->hasMethod($funcName)) {
                    $spreadAnalysis = $this->analyzeThisMethodSpread($funcName);

                    if ($spreadAnalysis !== null) {
                        $this->syncAnalysisMaps(
                            $properties, $enumResources, $nestedResources,
                            $directEnumFqcns, $modelFqcns, $customImports,
                            $spreadAnalysis,
                        );
                    }
                }

                continue;
            }

            // Handle $this->merge([...]) or $this->mergeWhen(condition, [...])
            if ($item->key === null && $item->value instanceof MethodCall) {
                $mergeResult = $this->analyzeMergeExpression($item->value);

                $this->syncAnalysisMaps(
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports,
                    $mergeResult,
                );

                continue;
            }

            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key);

            if ($keyName === null) {
                continue;
            }

            $result = $this->analyzeValueExpression($item->value);

            // When a child key overrides a parent spread key, clear stale parent tracking
            unset($enumResources[$keyName], $nestedResources[$keyName], $directEnumFqcns[$keyName], $modelFqcns[$keyName]);

            $properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $result['optional'],
                'description' => '',
            ];

            $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns);

        }

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns
        );
    }

    /**
     * Merge a ResourceAnalysis result into the running accumulator arrays.
     *
     * @param  ResourcePropertyInfoList  $properties
     * @param  ClassMapType  $enumResources
     * @param  ClassMapType  $nestedResources
     * @param  ClassMapType  $directEnumFqcns
     * @param  ClassMapType  $modelFqcns
     * @param  ImportMapType  $customImports
     */
    protected function syncAnalysisMaps(
        array &$properties,
        array &$enumResources,
        array &$nestedResources,
        array &$directEnumFqcns,
        array &$modelFqcns,
        array &$customImports,
        ResourceAnalysis $source,
    ): void {
        $properties = [...$properties, ...$source->properties];
        $enumResources = [...$enumResources, ...$source->enumResources];
        $nestedResources = [...$nestedResources, ...$source->nestedResources];
        $directEnumFqcns = [...$directEnumFqcns, ...$source->directEnumFqcns];
        $modelFqcns = [...$modelFqcns, ...$source->modelFqcns];

        foreach ($source->customImports as $path => $types) {
            $customImports[$path] = [...($customImports[$path] ?? []), ...$types];
        }
    }

    /**
     * Analyze a value expression and return its type + optional status.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeValueExpression(Expr $expr): array
    {
        $result = $this->unknownResult();

        // First-class callables (e.g. $this->when(...)) have no args — bail early
        if ($expr instanceof MethodCall && $expr->isFirstClassCallable()) {
            return $result; // @codeCoverageIgnore
        }

        // Closures / arrow functions — collect all return expressions and build a union type
        $closureReturns = $this->resolveClosureReturnExpressions($expr);

        if ($closureReturns !== []) {
            if (count($closureReturns) === 1) {
                return $this->analyzeValueExpression($closureReturns[0]);
            }

            return $this->analyzeClosureUnion($closureReturns);
        }

        // $this->when(condition, value) or $this->when(condition, value, default)
        if ($this->isThisMethodCall($expr, 'when')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhen($expr);
        }

        // $this->whenHas('attribute')
        if ($this->isThisMethodCall($expr, 'whenHas')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenHas($expr);
        }

        // $this->whenNotNull($this->value)
        if ($this->isThisMethodCall($expr, 'whenNotNull')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenNotNull($expr);
        }

        // $this->whenLoaded('relation') or $this->whenLoaded('relation', value)
        if ($this->isThisMethodCall($expr, 'whenLoaded')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenLoaded($expr);
        }

        // $this->whenCounted('relation')
        if ($this->isThisMethodCall($expr, 'whenCounted')) {
            return ['type' => 'number', 'optional' => true];
        }

        // $this->whenAggregated('relation', 'column', 'function')
        if ($this->isThisMethodCall($expr, 'whenAggregated')) {
            return ['type' => 'number', 'optional' => true];
        }

        // $this->whenPivotLoaded('table', fn) or $this->whenPivotLoadedAs(...)
        if ($this->isThisMethodCall($expr, 'whenPivotLoaded') || $this->isThisMethodCall($expr, 'whenPivotLoadedAs')) {
            return ['type' => 'unknown', 'optional' => true];
        }

        // $variable::staticMethod() — resolve against the related model in a whenLoaded closure context
        // Must come before the general StaticCall handler which only handles class-name static calls.
        if ($this->closureRelationModelClass !== null
            && $expr instanceof StaticCall
            && $expr->class instanceof Variable
            && is_string($expr->class->name)
            && $expr->class->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeRelatedModelMethodCall($expr->name->toString());
        }

        // EnumResource::make($this->prop) or SomeResource::make/collection()
        if ($expr instanceof StaticCall) {
            return $this->analyzeStaticCall($expr);
        }

        // new SomeResource($this->prop)
        if ($expr instanceof New_) {
            return $this->analyzeNewResource($expr);
        }

        // $this->property
        if ($this->isThisPropertyFetch($expr)) {
            return $this->analyzeThisProperty($expr);
        }

        // $this->relation->only([...]) or $this->relation?->only([...])
        if (($expr instanceof MethodCall || $expr instanceof NullsafeMethodCall)
            && $expr->name instanceof Identifier
            && in_array($expr->name->toString(), $this->supportedAttributeFilters(), true)
            && $expr->var instanceof PropertyFetch
            && $expr->var->var instanceof Variable
            && $expr->var->var->name === 'this'
        ) {
            return $this->analyzeRelationFilter($expr);
        }

        // Inline array literal → recursively build inline object type { key: type; ... }
        if ($expr instanceof Array_) {
            return $this->analyzeInlineArray($expr);
        }

        // $this->anyProp->subProp — e.g. $this->resource->name / ->value on a backed enum
        if ($expr instanceof PropertyFetch && $this->isThisPropertyFetch($expr->var)) {
            $info = $this->analyzeWrappedEnumResourceProperty($expr);

            if ($info['type'] === 'unknown') {
                $info = $this->analyzeWrappedModelResourceProperty($expr);
            }

            // When inside a whenLoaded closure and the wrapped-class resolution returned unknown,
            // try resolving the inner property against the related model (e.g. $this->user->name
            // where 'user' is the loaded relation).
            if ($info['type'] === 'unknown' && $this->closureRelationModelClass !== null && $expr->name instanceof Identifier) {
                $info = $this->analyzeRelatedModelProperty($expr->name->toString());
            }

            return $info;
        }

        // $this->anyProp->method() — e.g. $this->resource->extensions() on a backed enum or model
        if ($expr instanceof MethodCall
            && $this->isThisPropertyFetch($expr->var)
            && $expr->name instanceof Identifier
        ) {
            $info = $this->analyzeWrappedResourceMethodCall($expr);

            // When inside a whenLoaded closure and the wrapped-class resolution returned unknown,
            // try resolving the method against the related model (e.g. $this->user->nameTitled()).
            if ($info['type'] === 'unknown' && $this->closureRelationModelClass !== null) {
                $info = $this->analyzeRelatedModelMethodCall($expr->name->toString());
            }

            return $info;
        }

        // Generic $this->method() — infer from the method's declared return type via reflection.
        // Only reached for methods NOT already handled by the isThisMethodCall() guards above.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && $expr->var->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeThisMethodCall($expr->name->toString());
        }

        // $variable->property — resolve against the related model in a whenLoaded closure context
        if ($this->closureRelationModelClass !== null
            && $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeRelatedModelProperty($expr->name->toString());
        }

        // $variable->method() — resolve against the related model in a whenLoaded closure context
        if ($this->closureRelationModelClass !== null
            && $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeRelatedModelMethodCall($expr->name->toString());
        }

        return $result;
    }

    /**
     * Analyze $this->when(condition, value) — the value is the second arg.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhen(MethodCall $call): array
    {
        $result = $this->unknownResult();
        $args = $call->getArgs();

        if (count($args) >= 2) {
            $valueExpr = $args[1]->value;

            // Resolve the type from the value expression
            $inner = $this->analyzeValueExpression($valueExpr);
            $inner['optional'] = true;

            return $inner;
        }

        return [...$result, 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenHas('attribute') — the attribute name is the first arg string.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenHas(MethodCall $call): array
    {
        $result = $this->unknownResult();
        $args = $call->getArgs();

        if (count($args) >= 1 && $args[0]->value instanceof String_) {
            $attrName = $args[0]->value->value;
            $info = $this->resolveModelAttributeTypeInfo($attrName);
            $result = ['type' => $info['type'], 'optional' => true];

            if ($info['enumFqcn'] !== null) {
                $result['directEnumFqcn'] = $info['enumFqcn'];
            }

            return $result;
        }

        return [...$result, 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenNotNull($this->value) — resolve the inner expression type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenNotNull(MethodCall $call): array
    {
        $result = $this->unknownResult();
        $args = $call->getArgs();

        if (count($args) >= 1) {
            $inner = $this->analyzeValueExpression($args[0]->value);
            $inner['optional'] = true;

            return $inner;
        }

        return [...$result, 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenLoaded('relation') or $this->whenLoaded('relation', value, default).
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenLoaded(MethodCall $call): array
    {
        $result = $this->unknownResult();
        $args = $call->getArgs();

        // $this->whenLoaded('relation', value) — use value for type
        if (count($args) >= 2) {
            // Resolve the related model from the relation name so property/method
            // accesses on local variables inside the closure can be typed.
            $previousRelationModel = $this->closureRelationModelClass;

            if ($args[0]->value instanceof String_) {
                $info = $this->resolveModelRelationTypeInfo($args[0]->value->value);

                if (($info['modelFqcn'] ?? null) !== null) {
                    $this->closureRelationModelClass = $info['modelFqcn'];
                }
            }

            $inner = $this->analyzeValueExpression($args[1]->value);
            $inner['optional'] = true;

            $this->closureRelationModelClass = $previousRelationModel;

            return $inner;
        }

        // $this->whenLoaded('relation') — resolve relation type from model
        if (count($args) >= 1 && $args[0]->value instanceof String_) {
            $relationName = $args[0]->value->value;
            $info = $this->resolveModelRelationTypeInfo($relationName);
            $result = ['type' => $info['type'], 'optional' => true];

            if ($info['modelFqcn'] !== null) {
                $result['modelFqcn'] = $info['modelFqcn'];
            }

            return $result;
        }

        return [...$result, 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->merge([...]) or $this->mergeWhen(condition, [...]) — extract properties from the array arg.
     *
     * merge(array): unconditional — properties are NOT optional.
     * mergeWhen(condition, array): conditional — properties ARE optional.
     */
    protected function analyzeMergeExpression(MethodCall $call): ResourceAnalysis
    {
        $isMerge = $this->isThisMethodCall($call, 'merge');
        $isMergeWhen = $this->isThisMethodCall($call, 'mergeWhen');

        if (! $isMerge && ! $isMergeWhen) {
            return new ResourceAnalysis; // @codeCoverageIgnore
        }

        if ($call->isFirstClassCallable()) {
            return new ResourceAnalysis; // @codeCoverageIgnore
        }

        $args = $call->getArgs();

        // merge(array) — 1 arg, not optional
        if ($isMerge && count($args) >= 1) {
            return $this->resolveArrayOrClosureToProperties($args[0]->value, optional: false);
        }

        // mergeWhen(condition, array) — 2 args, optional
        if ($isMergeWhen && count($args) >= 2) {
            return $this->resolveArrayOrClosureToProperties($args[1]->value, optional: true);
        }

        return new ResourceAnalysis;
    }

    /**
     * Resolve an expression that's either an Array_ literal or a closure returning an Array_ into properties.
     * Handles multi-return closures (e.g. guard clause + data branch) by merging all branches.
     */
    protected function resolveArrayOrClosureToProperties(Expr $expr, bool $optional): ResourceAnalysis
    {
        if ($expr instanceof Array_) {
            return $this->extractPropertiesFromArray($expr, $optional);
        }

        $returnExprs = $this->resolveClosureReturnExpressions($expr);

        // Filter to non-empty Array_ expressions (skip guard clause `return []`)
        /** @var list<Array_> $arrays */
        $arrays = array_values(array_filter($returnExprs, fn (Expr $e) => $e instanceof Array_ && count($e->items) > 0));

        if ($arrays === []) {
            return new ResourceAnalysis;
        }

        if (count($arrays) === 1) {
            return $this->extractPropertiesFromArray($arrays[0], $optional);
        }

        // Multiple array branches — merge with union semantics
        $analyses = array_map(fn (Array_ $a) => $this->extractPropertiesFromArray($a, $optional), $arrays);

        return $this->mergeReturnBranches($analyses);
    }

    /**
     * Analyze a static method call like EnumResource::make() or SomeResource::make/collection().
     *
     * @return ValueExpressionResult
     */
    protected function analyzeStaticCall(StaticCall $call): array
    {
        $result = $this->unknownResult();
        $className = $this->resolveStaticCallClassName($call);
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($className === null || $methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        // EnumResource::make($this->prop)
        if ($this->isEnumResourceClass($className) && $methodName === 'make') {
            return $this->analyzeEnumResourceMake($call);
        }

        // SomeResource::make($this->prop) — nested resource
        if ($this->isResourceClass($className) && $methodName === 'make') {
            $resourceName = class_basename($className);
            $optional = $this->hasConditionalArgument($call);

            /** @var class-string $className */
            return [
                ...$result,
                'type' => $resourceName,
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // SomeResource::collection(...) — array of nested resource
        if ($this->isResourceClass($className) && $methodName === 'collection') {
            $resourceName = class_basename($className);
            $optional = $this->hasConditionalArgument($call);

            /** @var class-string $className */
            return [
                ...$result,
                'type' => $resourceName.'[]',
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        return $result;
    }

    /**
     * Analyze `new SomeResource(...)` — resolve as a nested resource.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeNewResource(New_ $expr): array
    {
        $result = $this->unknownResult();

        if (! $expr->class instanceof Name) {
            return $result; // @codeCoverageIgnore
        }

        $className = $expr->class->toString();

        // new EnumResource($this->prop)
        if ($this->isEnumResourceClass($className)) {
            $args = $expr->getArgs();

            if (count($args) >= 1) {
                return $this->resolveEnumFromPropertyArg($args[0]->value) ?? $result;
            }

            return $result;
        }

        if (! $this->isResourceClass($className)) {
            return $result; // @codeCoverageIgnore
        }

        $resourceName = class_basename($className);
        $optional = $this->hasConditionalNewArgument($expr);

        /** @var class-string $className */
        return [
            ...$result,
            'type' => $resourceName,
            'optional' => $optional,
            'resourceFqcn' => $className,
        ];
    }

    /**
     * Analyze EnumResource::make($this->prop) — resolve the enum class from the model property.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeEnumResourceMake(StaticCall $call): array
    {
        $result = $this->unknownResult();

        if ($call->isFirstClassCallable()) {
            return $result;
        }

        $args = $call->getArgs();

        if (count($args) < 1) {
            return $result;
        }

        return $this->resolveEnumFromPropertyArg($args[0]->value) ?? $result;
    }

    /**
     * Resolve an enum type from a $this->property expression (shared by EnumResource::make and new EnumResource).
     *
     * @return ValueExpressionResult|null
     */
    protected function resolveEnumFromPropertyArg(Expr $argExpr): ?array
    {
        $result = $this->unknownResult();

        if (! $this->isThisPropertyFetch($argExpr)) {
            return null;
        }

        /** @var PropertyFetch $argExpr */
        $propName = $argExpr->name instanceof Identifier ? $argExpr->name->toString() : null;

        if ($propName === null) {
            return null; // @codeCoverageIgnore
        }

        $info = $this->resolveModelAttributeTypeInfo($propName);

        if ($info['enumFqcn'] === null) {
            return null;
        }

        return [
            ...$result,
            'type' => $info['type'],
            'enumFqcn' => $info['enumFqcn'],
        ];
    }

    /**
     * Analyze $this->property — resolve the type from the backing model.
     *
     * Resolution order (matches Laravel's Model::__get):
     * 1. Model attributes (DB columns, accessors, mutators)
     * 2. Model relations
     *
     * @return ValueExpressionResult
     */
    protected function analyzeThisProperty(Expr $expr): array
    {
        $result = $this->unknownResult();

        /** @var PropertyFetch $expr */
        $propName = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($propName === null) {
            return $result; // @codeCoverageIgnore
        }

        // 0. Handle $this->collection in ResourceCollection subclasses
        if ($propName === 'collection' && $this->isResourceCollection()) {
            return $this->analyzeCollectionProperty();
        }

        // 1. Try model attributes (DB columns, accessors, mutators)
        $info = $this->resolveModelAttributeTypeInfo($propName);

        if ($info['type'] !== 'unknown') {
            $result = [
                ...$result,
                'type' => $info['type'],
            ];

            if ($info['enumFqcn'] !== null) {
                $result['directEnumFqcn'] = $info['enumFqcn'];
            }

            return $result;
        }

        // 2. Fall back to model relations
        $relationInfo = $this->resolveModelRelationTypeInfo($propName);

        if ($relationInfo['type'] !== 'unknown') {
            $result = [
                ...$result,
                'type' => $relationInfo['type'],
            ];

            if ($relationInfo['modelFqcn'] !== null) {
                $result['modelFqcn'] = $relationInfo['modelFqcn'];
            }

            return $result;
        }

        return $result;
    }

    /**
     * Extract properties and FQCNs from an array expression, e.g. for mergeWhen's second argument.
     */
    protected function extractPropertiesFromArray(Array_ $array, bool $optional = false): ResourceAnalysis
    {
        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $enumResources */
        $enumResources = [];
        /** @var ClassMapType $nestedResources */
        $nestedResources = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];

        foreach ($array->items as $item) {
            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key);

            if ($keyName === null) {
                continue;
            }

            $result = $this->analyzeValueExpression($item->value);

            $properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $optional || $result['optional'],
                'description' => '',
            ];

            $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns);
        }

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
        );
    }

    /**
     * Resolve and analyze the parent resource's toArray() method.
     */
    protected function analyzeParentToArray(): ?ResourceAnalysis
    {
        $parentClass = $this->resourceReflection->getParentClass();

        if ($parentClass === false || ! is_a($parentClass->getName(), JsonResource::class, true)) {
            return null; // @codeCoverageIgnore
        }

        if ($parentClass->getName() === JsonResource::class) {
            return $this->buildModelDelegatedAnalysis();
        }

        $parentAnalyzer = new self(
            $parentClass,
            $this->modelClass,
        );

        return $parentAnalyzer->analyze();
    }

    /**
     * Resolve and analyze a $this->method() spread from a trait or the class itself.
     */
    protected function analyzeThisMethodSpread(string $methodName): ?ResourceAnalysis
    {
        if (! $this->resourceReflection->hasMethod($methodName)) {
            return null; // @codeCoverageIgnore
        }

        $method = $this->resourceReflection->getMethod($methodName);
        $filePath = $method->getFileName();

        if ($filePath === false) {
            return null; // @codeCoverageIgnore
        }

        $source = (string) file_get_contents($filePath);
        $stmts = $this->parseAndResolveAst($source);

        $finder = new NodeFinder;
        $targetMethod = $finder->findFirst($stmts, function (Node $node) use ($methodName): bool {
            return $node instanceof ClassMethod && $node->name->toString() === $methodName;
        });

        if (! $targetMethod instanceof ClassMethod || $targetMethod->stmts === null) {
            return null; // @codeCoverageIgnore
        }

        $returnStmt = $finder->findFirst($targetMethod->stmts, function (Node $node): bool {
            return $node instanceof Return_;
        });

        if ($returnStmt instanceof Return_ && $returnStmt->expr instanceof Array_) {
            $analysis = $this->analyzeReturnArray($returnStmt->expr);
        } elseif ($returnStmt instanceof Return_ && $returnStmt->expr instanceof Variable
            && is_string($returnStmt->expr->name)) {
            $analysis = $this->resolveVariableReturnAnalysis($targetMethod->stmts, $returnStmt->expr->name);
        } else {
            $analysis = new ResourceAnalysis;
        }

        // Apply PHPDoc @return array shape types to resolve unknown property types
        $docTypes = $this->parseReturnArrayShape($method);

        if ($docTypes !== []) {
            $tsMap = LaravelTsPublish::typesMap();

            foreach ($analysis->properties as &$prop) {
                if ($prop['type'] !== 'unknown' || ! isset($docTypes[$prop['name']])) {
                    continue;
                }

                $prop['type'] = $this->resolvePhpDocType($docTypes[$prop['name']], $tsMap);
            }

            unset($prop);
        }

        // Apply #[TsResourceCasts] attribute overrides from the method
        foreach ($method->getAttributes(TsResourceCasts::class) as $attr) {
            $instance = $attr->newInstance();

            foreach ($instance->types as $property => $value) {
                $type = is_array($value) ? $value['type'] : $value;
                $optional = is_array($value) && isset($value['optional']) ? (bool) $value['optional'] : null;

                $found = false;

                foreach ($analysis->properties as &$prop) {
                    if ($prop['name'] === $property) {
                        $prop['type'] = $type;

                        if ($optional !== null) {
                            $prop['optional'] = $optional;
                        }

                        $found = true;

                        break;
                    }
                }

                unset($prop);

                if (! $found) {
                    $analysis->properties[] = [
                        'name' => $property,
                        'type' => $type,
                        'optional' => $optional ?? false,
                        'description' => '',
                    ];
                }

                if (is_array($value) && isset($value['import'])) {
                    foreach (LaravelTsPublish::extractImportableTypes($type) as $importName) {
                        $analysis->customImports[$value['import']][] = $importName;
                    }
                }
            }
        }

        return $analysis;
    }

    /**
     * Resolve properties from a method that builds an array variable and returns it.
     *
     * Handles: $data = [...]; $data['key'] = expr; if (...) { $data['key'] = expr; } return $data;
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function resolveVariableReturnAnalysis(array $stmts, string $varName): ResourceAnalysis
    {
        /** @var ResourcePropertyInfoList $properties */
        $properties = [];
        /** @var ClassMapType $enumResources */
        $enumResources = [];
        /** @var ClassMapType $nestedResources */
        $nestedResources = [];
        /** @var ClassMapType $directEnumFqcns */
        $directEnumFqcns = [];
        /** @var ClassMapType $modelFqcns */
        $modelFqcns = [];
        /** @var ImportMapType $customImports */
        $customImports = [];

        $this->collectVariableArrayAssignments(
            $stmts, $varName, false,
            $properties, $enumResources, $nestedResources,
            $directEnumFqcns, $modelFqcns, $customImports,
        );

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
        );
    }

    /**
     * Recursively collect array assignments to a variable from method statements.
     *
     * Assignments inside if/elseif/else blocks are marked as optional.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  ResourcePropertyInfoList  $properties
     * @param  ClassMapType  $enumResources
     * @param  ClassMapType  $nestedResources
     * @param  ClassMapType  $directEnumFqcns
     * @param  ClassMapType  $modelFqcns
     * @param  ImportMapType  $customImports
     */
    protected function collectVariableArrayAssignments(
        array $stmts,
        string $varName,
        bool $isConditional,
        array &$properties,
        array &$enumResources,
        array &$nestedResources,
        array &$directEnumFqcns,
        array &$modelFqcns,
        array &$customImports,
    ): void {
        foreach ($stmts as $stmt) {
            if (! $stmt instanceof ExpressionStmt && ! $stmt instanceof If_
                && ! $stmt instanceof Foreach_ && ! $stmt instanceof For_
                && ! $stmt instanceof While_ && ! $stmt instanceof Do_) {
                continue;
            }

            // $var = [...] — base array assignment
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof Variable
                && $stmt->expr->var->name === $varName
                && $stmt->expr->expr instanceof Array_) {
                $baseAnalysis = $this->analyzeReturnArray($stmt->expr->expr);

                if ($isConditional) {
                    foreach ($baseAnalysis->properties as &$prop) {
                        $prop['optional'] = true;
                    }

                    unset($prop);
                }

                $this->syncAnalysisMaps(
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports,
                    $baseAnalysis,
                );

                continue;
            }

            // $var['key'] = expr — individual key assignment
            if ($stmt instanceof ExpressionStmt
                && $stmt->expr instanceof Assign
                && $stmt->expr->var instanceof ArrayDimFetch
                && $stmt->expr->var->var instanceof Variable
                && $stmt->expr->var->var->name === $varName
                && $stmt->expr->var->dim instanceof String_) {
                $keyName = $stmt->expr->var->dim->value;
                $result = $this->analyzeValueExpression($stmt->expr->expr);
                $optional = $isConditional || $result['optional'];

                $existingIndex = null;

                foreach ($properties as $index => $existing) {
                    if ($existing['name'] === $keyName) {
                        $existingIndex = $index;

                        break;
                    }
                }

                if ($existingIndex !== null) {
                    $properties[$existingIndex] = [
                        'name' => $keyName,
                        'type' => $result['type'],
                        'optional' => $properties[$existingIndex]['optional'] && $optional,
                        'description' => '',
                    ];
                } else {
                    $properties[] = [
                        'name' => $keyName,
                        'type' => $result['type'],
                        'optional' => $optional,
                        'description' => '',
                    ];
                }

                unset(
                    $enumResources[$keyName],
                    $nestedResources[$keyName],
                    $directEnumFqcns[$keyName],
                    $modelFqcns[$keyName],
                );

                $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns);

                continue;
            }

            // if/elseif/else — recurse with isConditional = true
            if ($stmt instanceof If_) {
                $this->collectVariableArrayAssignments(
                    $stmt->stmts, $varName, true,
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports,
                );

                foreach ($stmt->elseifs as $elseif) {
                    $this->collectVariableArrayAssignments(
                        $elseif->stmts, $varName, true,
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports,
                    );
                }

                if ($stmt->else !== null) {
                    $this->collectVariableArrayAssignments(
                        $stmt->else->stmts, $varName, true,
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports,
                    );
                }
            }

            // Loop bodies — recurse with isConditional = true (loops may execute 0 times)
            if ($stmt instanceof Foreach_ || $stmt instanceof For_
                || $stmt instanceof While_ || $stmt instanceof Do_) {
                $this->collectVariableArrayAssignments(
                    $stmt->stmts, $varName, true,
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports,
                );
            }
        }
    }

    /**
     * Parse a @return array shape PHPDoc annotation into a property-name → PHP-type map.
     *
     * Supports: @return array{key: type, key2: type2, ...}
     *
     * @return array<string, string>
     */
    protected function parseReturnArrayShape(\ReflectionMethod $method): array
    {
        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return [];
        }

        if (! preg_match('/@return\s+array\{([^}]+)\}/', $docComment, $matches)) {
            return [];
        }

        $result = [];
        $entries = explode(',', $matches[1]);

        foreach ($entries as $entry) {
            $entry = trim($entry);
            $entry = (string) preg_replace('/^\*\s*/', '', $entry);

            if (! str_contains($entry, ':')) {
                continue;
            }

            [$key, $type] = explode(':', $entry, 2);
            $result[trim($key)] = trim($type);
        }

        return $result;
    }

    /**
     * Convert a PHPDoc type string (e.g. "string|null") to its TypeScript equivalent.
     *
     * @param  array<string, string|(callable(): string)>  $tsMap
     */
    protected function resolvePhpDocType(string $phpType, array $tsMap): string
    {
        $parts = array_map('trim', explode('|', $phpType));
        $resolved = [];

        foreach ($parts as $part) {
            $lower = strtolower($part);
            $mapped = $tsMap[$lower] ?? null;

            if (is_string($mapped)) {
                $resolved[] = $mapped;
            } elseif (is_callable($mapped)) {
                $resolved[] = (string) $mapped();
            } else {
                $resolved[] = $part;
            }
        }

        return implode(' | ', array_unique($resolved));
    }

    protected function isResourceCollection(): bool
    {
        return $this->resourceReflection->isSubclassOf(ResourceCollection::class);
    }

    /**
     * Resolve the singular resource FQCN for a ResourceCollection.
     *
     * Checks the explicit $collects property first, then falls back
     * to naming convention (UserCollection → UserResource).
     *
     * @return class-string<JsonResource>|null
     */
    protected function resolveSingularResourceClass(): ?string
    {
        /** @var array<string, mixed> $defaults */
        $defaults = $this->resourceReflection->getDefaultProperties();
        $collects = $defaults['collects'] ?? null;

        if (is_string($collects) && class_exists($collects) && is_a($collects, JsonResource::class, true)) {
            return $collects;
        }

        $className = $this->resourceReflection->getShortName();
        $namespace = $this->resourceReflection->getNamespaceName();

        if (str_ends_with($className, 'Collection')) {
            $base = substr($className, 0, -10);

            $candidate = $namespace.'\\'.$base.'Resource';

            if (class_exists($candidate) && is_a($candidate, JsonResource::class, true)) {
                return $candidate;
            }

            $candidate = $namespace.'\\'.$base; // @codeCoverageIgnoreStart

            if (class_exists($candidate) && is_a($candidate, JsonResource::class, true)) {
                return $candidate;
            } // @codeCoverageIgnoreEnd
        }

        return null;
    }

    /**
     * Analyze $this->collection in a ResourceCollection, resolving it
     * to the singular resource type as an array.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeCollectionProperty(): array
    {
        $result = $this->unknownResult();
        $singular = $this->resolveSingularResourceClass();

        if ($singular === null) {
            return $result;
        }

        return [
            ...$result,
            'type' => class_basename($singular).'[]',
            'resourceFqcn' => $singular,
        ];
    }

    /**
     * Analyze `$this->relation->only([...])` or `$this->relation?->only([...])`.
     *
     * Resolves the relation's model class, filters it to the specified keys,
     * and returns an inline TypeScript type like `{ id: number; name: string }`.
     * Nullable chaining (`?->`) appends `| null` to the type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeRelationFilter(MethodCall|NullsafeMethodCall $call): array
    {
        $result = $this->unknownResult();

        $nullable = $call instanceof NullsafeMethodCall;
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        /** @var PropertyFetch $varExpr */
        $varExpr = $call->var;
        $propName = $varExpr->name instanceof Identifier ? $varExpr->name->toString() : null;

        if ($propName === null) {
            return $result; // @codeCoverageIgnore
        }

        $relationInfo = $this->resolveModelRelationTypeInfo($propName);
        $modelFqcn = $relationInfo['modelFqcn'] ?? $this->resolveAccessorModelFqcn($propName);

        if ($modelFqcn === null) {
            return $result; // @codeCoverageIgnore
        }

        $keys = $this->extractFilterKeys($call);

        if ($keys === null || $keys === []) {
            return $result; // @codeCoverageIgnore
        }

        $include = $methodName === 'only';
        $filterResult = $this->resolveFilteredRelationType($modelFqcn, $keys, $include);
        $inlineType = $filterResult['type'];

        if ($nullable && $inlineType !== 'unknown') {
            $inlineType .= ' | null';
        }

        return [
            ...$result,
            'type' => $inlineType,
            'embeddedEnumFqcns' => $filterResult['enumFqcns'],
            'embeddedModelFqcns' => $filterResult['modelFqcns'],
        ];
    }

    /**
     * Dispatch FQCN results from a value expression into the tracking maps.
     *
     * @param  ValueExpressionResult  $result
     * @param  ClassMapType  $enumResources
     * @param  ClassMapType  $directEnumFqcns
     * @param  ClassMapType  $nestedResources
     * @param  ClassMapType  $modelFqcns
     */
    protected function dispatchFqcnResults(
        string $keyName,
        array $result,
        array &$enumResources,
        array &$directEnumFqcns,
        array &$nestedResources,
        array &$modelFqcns,
    ): void {
        if (isset($result['enumFqcn'])) {
            $enumResources[$keyName] = $result['enumFqcn'];
        }

        if (isset($result['directEnumFqcn'])) {
            $directEnumFqcns[$keyName] = $result['directEnumFqcn'];
        }

        if (isset($result['resourceFqcn'])) {
            $nestedResources[$keyName] = $result['resourceFqcn'];
        }

        if (isset($result['modelFqcn'])) {
            $modelFqcns[$keyName] = $result['modelFqcn'];
        }

        // Embedded FQCNs from inline relation filter types (e.g. $this->post->only([...])).
        // Using FQCN as both key and value: ResourceTransformer only reads the value, never the key.
        foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
            $directEnumFqcns[$fqcn] = $fqcn;
        }

        foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
            $modelFqcns[$fqcn] = $fqcn;
        }

        foreach ($result['embeddedResourceFqcns'] ?? [] as $fqcn) {
            $nestedResources[$fqcn] = $fqcn;
        }
    }

    /**
     * Analyze an inline array literal and produce an inline TypeScript object type.
     *
     * e.g. `['name' => $this->resource->name, 'value' => $this->maxSizeMb()]`
     * becomes `{ name: string; value: number }`.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeInlineArray(Array_ $array): array
    {
        $analysis = $this->analyzeReturnArray($array);

        if ($analysis->properties === []) {
            return ['type' => 'Record<string, unknown>', 'optional' => false];
        }

        $parts = array_map(function (array $prop): string {
            $key = LaravelTsPublish::validJsObjectKey($prop['name']);

            return $prop['optional']
                ? "{$key}?: {$prop['type']}"
                : "{$key}: {$prop['type']}";
        }, $analysis->properties);

        $result = ['type' => '{ '.implode('; ', $parts).' }', 'optional' => false];

        // Propagate import metadata from the inner analysis so that enum, model,
        // and resource FQCNs referenced inside the inline object reach the outer
        // ResourceAnalysis and generate the correct import statements.
        $embeddedEnumFqcns = array_values(array_unique([
            ...array_values($analysis->directEnumFqcns),
            ...array_values($analysis->enumResources),
        ]));

        $embeddedModelFqcns = array_values(array_unique(
            array_values($analysis->modelFqcns),
        ));

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        if ($embeddedModelFqcns !== []) {
            $result['embeddedModelFqcns'] = $embeddedModelFqcns;
        }

        // Nested resources (e.g. SomeResource::make() inside the inline array)
        // are tracked separately so they can be merged into the outer analysis'
        // resource imports rather than model imports.
        if ($analysis->nestedResources !== []) {
            $result['embeddedResourceFqcns'] = array_values(array_unique(
                array_values($analysis->nestedResources),
            ));
        }

        return $result;
    }

    /**
     * Analyze `$this->anyProp->subProp` — a property fetch on one of `$this`'s properties.
     *
     * Handles PHP enum universal properties:
     *   - `->name`  is always `string` (every PHP enum has it)
     *   - `->value` type depends on the enum's backing type (string or int)
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWrappedEnumResourceProperty(PropertyFetch $expr): array
    {
        $result = $this->unknownResult();
        $innerProp = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($innerProp === null) {
            return $result; // @codeCoverageIgnore
        }

        // Only apply enum-specific logic when the wrapped type is actually a PHP enum.
        // Without this guard, model-backed resources that use $this->resource->column
        // would silently receive 'string' instead of the correct column type or 'unknown'.
        $wrappedClass = $this->resolveWrappedClass();

        if ($wrappedClass === null || ! enum_exists($wrappedClass)) {
            return $result;
        }

        if ($innerProp === 'name') {
            return [
                ...$result,
                'type' => 'string',
            ];
        }

        if ($innerProp === 'value') {
            return [
                ...$result,
                'type' => $this->resolveEnumValueBackingType(),
            ];
        }

        return $result;
    }

    /**
     * Analyze `$this->anyProp->subProp` where `$this->anyProp` is a wrapped model resource
     * (i.e. has a `@var ModelType|null` docblock on `$resource`).
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWrappedModelResourceProperty(PropertyFetch $expr): array
    {
        $result = $this->unknownResult();
        $innerProp = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($innerProp === null) {
            return $result; // @codeCoverageIgnore
        }

        $wrappedClass = $this->resolveWrappedClass();

        if ($wrappedClass === null || ! class_exists($wrappedClass)) {
            return $result;
        }

        // Try to resolve as a model attribute (DB column, accessor, mutator)
        $info = $this->resolveModelAttributeTypeInfo($innerProp);

        if ($info['type'] !== 'unknown') {
            $result = ['type' => $info['type'], 'optional' => false];

            if ($info['enumFqcn'] !== null) {
                $result['directEnumFqcn'] = $info['enumFqcn']; // @codeCoverageIgnore
            }

            return $result;
        }

        return $result; // @codeCoverageIgnore
    }

    /**
     * Analyze `$this->anyProp->method()` by resolving the method on the wrapped class
     * (e.g. `$this->resource->extensions()` on an enum-backed resource).
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWrappedResourceMethodCall(MethodCall $expr): array
    {
        $result = $this->unknownResult();
        $methodName = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        $wrappedClass = $this->resolveWrappedClass();

        if ($wrappedClass === null || ! method_exists($wrappedClass, $methodName)) {
            return $result;
        }

        /** @var class-string $wrappedClass */
        $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($wrappedClass), $methodName);

        if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
            return [...$tsInfo, 'optional' => false];
        }

        return $result; // @codeCoverageIgnore
    }

    /**
     * Resolve a property access on a related model within a whenLoaded closure.
     *
     * Uses the same resolution chain as model attributes: accessor → cast → DB column type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeRelatedModelProperty(string $propertyName): array
    {
        if ($this->closureRelationModelClass === null) {
            return $this->unknownResult(); // @codeCoverageIgnore
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($this->closureRelationModelClass, $propertyName);

        if ($tsInfo['type'] === 'unknown') {
            return $this->unknownResult();
        }

        $info = ['type' => $tsInfo['type'], 'optional' => false];

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        if ($enumFqcn !== null) {
            $info['directEnumFqcn'] = $enumFqcn;
        }

        return $info;
    }

    /**
     * Resolve a method call (instance or static) on a related model within a whenLoaded closure.
     *
     * Uses `LaravelTsPublish::methodOrDocblockReturnTypes()` to resolve from the method's
     * declared return type hint.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeRelatedModelMethodCall(string $methodName): array
    {
        if ($this->closureRelationModelClass === null) {
            return $this->unknownResult(); // @codeCoverageIgnore
        }

        $tsInfo = resolve(ModelAttributeResolver::class)->resolveMethodReturnType($this->closureRelationModelClass, $methodName);

        if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
            return [...$tsInfo, 'optional' => false];
        }

        return $this->unknownResult(); // @codeCoverageIgnore
    }

    /**
     * Determine the TypeScript type for a backed enum's `->value` property by inspecting
     * the `@var` docblock on the resource's `$resource` property and resolving any short
     * class name via the file's use-statement map.
     */
    protected function resolveEnumValueBackingType(): string
    {
        $wrappedClass = $this->resolveWrappedClass();

        if ($wrappedClass !== null && enum_exists($wrappedClass)) {
            $r = new ReflectionEnum($wrappedClass);
            $backingType = $r->getBackingType();

            if ($backingType !== null) {
                return $backingType->getName() === 'string' ? 'string' : 'number';
            }
        }

        return 'string | number';
    }

    /**
     * Analyze a generic `$this->method()` call by reflecting on the method's declared
     * return type and mapping it to a TypeScript type.
     *
     * Checks the resource's own methods first, then falls back to the wrapped class
     * (e.g. the backing enum) for calls that are delegated via `__call`.
     *
     * Used as a fallback for resource instance methods that are not one of Laravel's
     * conditional helpers (`when`, `whenLoaded`, etc.).
     *
     * @return ValueExpressionResult
     */
    protected function analyzeThisMethodCall(string $methodName): array
    {
        // 1. Check the resource's own methods
        if ($this->resourceReflection->hasMethod($methodName)) {
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes($this->resourceReflection, $methodName);

            if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                return [
                    ...$tsInfo,
                    'optional' => false,
                ];
            }
        }

        // 2. Fall back to the wrapped class (e.g. backing enum) for delegated calls
        $wrappedClass = $this->resolveWrappedClass();

        if ($wrappedClass !== null && method_exists($wrappedClass, $methodName)) {
            /** @var class-string $wrappedClass */
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($wrappedClass), $methodName);

            if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                return [
                    ...$tsInfo,
                    'optional' => false,
                ];
            }
        }

        return $this->unknownResult(); // @codeCoverageIgnore
    }

    /**
     * Analyze all direct Return_ statements that yield Array_ literals and merge
     * their properties with union semantics.
     *
     * Empty array returns (guard clauses like `return []`) are filtered out.
     * If only one non-empty return exists, it is analyzed directly (unchanged behavior).
     * If multiple exist, each is analyzed separately and the results are merged:
     * properties present in ALL branches stay required; properties in only SOME become optional.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function analyzeAllReturnBranches(array $stmts): ?ResourceAnalysis
    {
        /** @var list<Return_> $candidates */
        $candidates = [];

        $this->collectDirectReturns($stmts, $candidates);

        // Filter out empty array returns (guard clauses like `return []`)
        $candidates = array_values(array_filter($candidates, function (Return_ $r): bool {
            return $r->expr instanceof Array_ && count($r->expr->items) > 0;
        }));

        if ($candidates === []) {
            return null;
        }

        // Single branch — analyze directly (unchanged behavior)
        if (count($candidates) === 1) {
            /** @var Array_ $expr */
            $expr = $candidates[0]->expr;

            return $this->analyzeReturnArray($expr);
        }

        // Multiple branches — analyze each, then merge with union semantics
        $analyses = array_map(function (Return_ $r) {
            /** @var Array_ $expr */
            $expr = $r->expr;

            return $this->analyzeReturnArray($expr);
        }, $candidates);

        return $this->mergeReturnBranches($analyses);
    }

    /**
     * Merge multiple ResourceAnalysis objects from different return branches.
     *
     * Properties present in ALL branches remain required (unless already optional).
     * Properties present in only SOME branches become optional.
     * Properties with different types across branches get their types unioned.
     *
     * @param  list<ResourceAnalysis>  $analyses
     */
    protected function mergeReturnBranches(array $analyses): ResourceAnalysis
    {
        $branchCount = count($analyses);

        // Collect property info per name across all branches
        /** @var array<string, list<array{type: string, optional: bool, description: string}>> */
        $propertyMap = [];

        $enumResources = [];
        $nestedResources = [];
        $directEnumFqcns = [];
        $modelFqcns = [];
        $customImports = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->properties as $prop) {
                $propertyMap[$prop['name']][] = $prop;
            }

            $enumResources = [...$enumResources, ...$analysis->enumResources];
            $nestedResources = [...$nestedResources, ...$analysis->nestedResources];
            $directEnumFqcns = [...$directEnumFqcns, ...$analysis->directEnumFqcns];
            $modelFqcns = [...$modelFqcns, ...$analysis->modelFqcns];

            foreach ($analysis->customImports as $path => $names) { // @codeCoverageIgnoreStart
                $customImports[$path] = array_values(array_unique([
                    ...($customImports[$path] ?? []),
                    ...$names,
                ]));
            } // @codeCoverageIgnoreEnd
        }

        // Build merged property list
        /** @var list<array{name: string, type: string, optional: bool, description: string}> */
        $properties = [];

        foreach ($propertyMap as $name => $entries) {
            // Union distinct types
            $types = array_values(array_unique(array_column($entries, 'type')));
            $type = count($types) === 1 ? $types[0] : implode(' | ', $types);

            // Optional if not present in ALL branches, or if any branch marks it optional
            $presentInAll = count($entries) === $branchCount;
            $anyOptional = (bool) array_filter($entries, fn (array $e) => $e['optional']);
            $optional = ! $presentInAll || $anyOptional;

            // Use the first non-empty description found
            $description = '';

            foreach ($entries as $entry) {
                if ($entry['description'] !== '') { // @codeCoverageIgnoreStart
                    $description = $entry['description'];

                    break; // @codeCoverageIgnoreEnd
                }
            }

            $properties[] = [
                'name' => $name,
                'type' => $type,
                'optional' => $optional,
                'description' => $description,
            ];
        }

        return new ResourceAnalysis(
            properties: $properties,
            enumResources: $enumResources,
            nestedResources: $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
        );
    }

    /**
     * Recursively collect Return_ statements with Array_ expressions from
     * the given statements, descending into control-flow structures (if, foreach, etc.)
     * but NOT into closures, arrow functions, or anonymous classes.
     *
     * @param  array<Node\Stmt|Node>  $stmts
     * @param  list<Return_>  $candidates
     */
    protected function collectDirectReturns(array $stmts, array &$candidates): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && $stmt->expr instanceof Array_) {
                $candidates[] = $stmt;

                continue;
            }

            // Descend into control-flow blocks (if/elseif/else, foreach, for, while, do-while)
            if ($stmt instanceof If_) {
                $this->collectDirectReturns($stmt->stmts, $candidates);

                foreach ($stmt->elseifs as $elseif) {
                    $this->collectDirectReturns($elseif->stmts, $candidates);
                }

                if ($stmt->else !== null) {
                    $this->collectDirectReturns($stmt->else->stmts, $candidates);
                }

                continue;
            }

            if ($stmt instanceof Foreach_ || $stmt instanceof For_ || $stmt instanceof While_ || $stmt instanceof Do_) {
                $this->collectDirectReturns($stmt->stmts, $candidates);
            }

            // Do NOT descend into closures, arrow functions, or anonymous classes
        }
    }

    /**
     * Extract an instanceof type hint from guard clauses in toArray().
     *
     * Supports patterns like:
     *   if (!$this->resource instanceof ClassName) { return []; }
     *
     * Falls back to resolving the short class name via the file's use-statement map.
     *
     * @return class-string|null
     */
    protected function resolveInstanceOfType(ClassMethod $method, NodeFinder $finder): ?string
    {
        /** @var list<If_> $ifNodes */
        $ifNodes = $finder->find($method->stmts ?? [], function (Node $node): bool {
            return $node instanceof If_;
        });

        foreach ($ifNodes as $ifNode) {
            $cond = $ifNode->cond;

            // Match: if (!$this->resource instanceof ClassName)
            if ($cond instanceof BooleanNot && $cond->expr instanceof Instanceof_) {
                $instanceOf = $cond->expr;
            } elseif ($cond instanceof Instanceof_) {
                // Match: if ($this->resource instanceof ClassName) — positive guard
                $instanceOf = $cond;
            } else {
                continue;
            }

            // Verify it's checking $this->resource
            if (! ($instanceOf->expr instanceof PropertyFetch
                && $instanceOf->expr->var instanceof Variable
                && $instanceOf->expr->var->name === 'this'
                && $instanceOf->expr->name instanceof Identifier
                && $instanceOf->expr->name->toString() === 'resource')) {
                continue; // @codeCoverageIgnore
            }

            if (! $instanceOf->class instanceof Name) {
                continue; // @codeCoverageIgnore
            }

            // After NameResolver traversal, the class name is already a FQCN
            $fqcn = $instanceOf->class->toString();

            if (class_exists($fqcn) || enum_exists($fqcn)) {
                return $fqcn;
            }
        }

        return null;
    }

    /**
     * Resolve the wrapped class for this resource, checking resolveClassOnProperty() first,
     * then falling back to the instanceof guard clause type hint.
     *
     * @return class-string|null
     */
    protected function resolveWrappedClass(): ?string
    {
        return $this->resolveClassOnProperty($this->resourceReflection) ?? $this->instanceOfWrappedClass;
    }

    /**
     * Merge multiple closure return expressions into a single ValueExpressionResult
     * with a union type. Each return branch is analyzed independently and their types
     * are joined with ` | `.
     *
     * Null returns (guard clauses) contribute `null` to the union instead of a full object shape.
     * Duplicate type strings are removed.
     *
     * Import metadata (enum/model FQCNs) is collected from all branches.
     *
     * @param  list<Expr>  $returns
     * @return ValueExpressionResult
     */
    protected function analyzeClosureUnion(array $returns): array
    {
        /** @var list<string> $types */
        $types = [];
        /** @var list<class-string> $embeddedEnumFqcns */
        $embeddedEnumFqcns = [];
        /** @var list<class-string> $embeddedModelFqcns */
        $embeddedModelFqcns = [];
        /** @var list<class-string> $embeddedResourceFqcns */
        $embeddedResourceFqcns = [];
        $hasNull = false;

        foreach ($returns as $returnExpr) {
            // Null literal → contributes `null` to the union
            if ($returnExpr instanceof ConstFetch
                && $returnExpr->name->toLowerString() === 'null') {
                $hasNull = true;

                continue;
            }

            $inner = $this->analyzeValueExpression($returnExpr);

            if ($inner['type'] === 'unknown') {
                continue; // @codeCoverageIgnore
            }

            // Collect the type string
            $types[] = $inner['type'];

            // Merge import metadata
            if (isset($inner['enumFqcn'])) { // @codeCoverageIgnoreStart
                $embeddedEnumFqcns[] = $inner['enumFqcn'];
            } // @codeCoverageIgnoreEnd

            if (isset($inner['directEnumFqcn'])) {
                $embeddedEnumFqcns[] = $inner['directEnumFqcn'];
            }

            if (isset($inner['embeddedEnumFqcns'])) {
                array_push($embeddedEnumFqcns, ...$inner['embeddedEnumFqcns']);
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

            if (isset($inner['modelFqcn'])) {
                $embeddedModelFqcns[] = $inner['modelFqcn'];
            }
        }

        if ($hasNull) {
            $types[] = 'null';
        }

        // Deduplicate types while preserving order
        $types = array_values(array_unique($types));

        if ($types === []) {
            return $this->unknownResult(); // @codeCoverageIgnore
        }

        $result = ['type' => implode(' | ', $types), 'optional' => false];

        $embeddedEnumFqcns = array_values(array_unique($embeddedEnumFqcns));
        $embeddedModelFqcns = array_values(array_unique($embeddedModelFqcns));
        $embeddedResourceFqcns = array_values(array_unique($embeddedResourceFqcns));

        if ($embeddedEnumFqcns !== []) {
            $result['embeddedEnumFqcns'] = $embeddedEnumFqcns;
        }

        if ($embeddedModelFqcns !== []) {
            $result['embeddedModelFqcns'] = $embeddedModelFqcns;
        }

        if ($embeddedResourceFqcns !== []) {
            $result['embeddedResourceFqcns'] = $embeddedResourceFqcns;
        }

        return $result;
    }

    /**
     * Fallback result for expressions that can't be analyzed or have no type information.
     *
     * @return ValueExpressionResult
     */
    protected function unknownResult(): array
    {
        return ['type' => 'unknown', 'optional' => false];
    }
}
