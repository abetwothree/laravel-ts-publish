<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\FiltersModelAttributes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ResolvesModelTypes;
use AbeTwoThree\LaravelTsPublish\Attributes\TsResourceCasts;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\FuncCall;
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
use PhpParser\Node\Stmt\Use_;
use PhpParser\Node\Stmt\While_;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use PhpParser\ParserFactory;
use ReflectionClass;

/**
 * Analyzes a JsonResource's toArray() method body to extract property names,
 * types, and conditional (optional) markers using AST parsing.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 * @phpstan-import-type ImportMapType from ResourceAnalysis
 *
 * @phpstan-type ValueExpressionResult = array{type: string, optional: bool, enumFqcn?: class-string, directEnumFqcn?: class-string, resourceFqcn?: class-string, modelFqcn?: class-string, embeddedEnumFqcns?: list<class-string>, embeddedModelFqcns?: list<class-string>}
 */
class ResourceAstAnalyzer
{
    use FiltersModelAttributes;
    use InspectsAstNodes;
    use ResolvesModelTypes;

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

        // Prefer a Return_ that yields an array literal (handles guard-then-return patterns like
        // `if ($this->resource === null) { return null; } return [...]`)
        $returnStmt = $finder->findFirst($toArrayMethod->stmts, function (Node $node): bool {
            return $node instanceof Return_ && $node->expr instanceof Array_;
        }) ?? $finder->findFirst($toArrayMethod->stmts, function (Node $node): bool {
            return $node instanceof Return_;
        });

        if (! $returnStmt instanceof Return_ || ! $returnStmt->expr instanceof Array_) {
            if ($returnStmt instanceof Return_ && $returnStmt->expr !== null && $this->isParentToArrayCall($returnStmt->expr)) {
                return $this->analyzeParentToArray() ?? $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
            }

            // return $this->only([...]) or return $this->except([...])
            if ($returnStmt instanceof Return_ && $returnStmt->expr instanceof MethodCall) {
                return $this->analyzeThisAttributeFilter($returnStmt->expr) ?? new ResourceAnalysis;
            }

            return new ResourceAnalysis;
        }

        return $this->analyzeReturnArray($returnStmt->expr);
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
        // First-class callables (e.g. $this->when(...)) have no args — bail early
        if ($expr instanceof MethodCall && $expr->isFirstClassCallable()) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
        }

        // Closures / arrow functions — resolve the return expression and recurse
        $closureReturn = $this->resolveClosureReturnExpression($expr);

        if ($closureReturn !== null) {
            return $this->analyzeValueExpression($closureReturn);
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
            return $this->analyzeWrappedResourceProperty($expr);
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

        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Analyze $this->when(condition, value) — the value is the second arg.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhen(MethodCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) >= 2) {
            $valueExpr = $args[1]->value;

            // Resolve the type from the value expression
            $inner = $this->analyzeValueExpression($valueExpr);
            $inner['optional'] = true;

            return $inner;
        }

        return ['type' => 'unknown', 'optional' => true];
    }

    /**
     * Analyze $this->whenHas('attribute') — the attribute name is the first arg string.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenHas(MethodCall $call): array
    {
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

        return ['type' => 'unknown', 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenNotNull($this->value) — resolve the inner expression type.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenNotNull(MethodCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) >= 1) {
            $inner = $this->analyzeValueExpression($args[0]->value);
            $inner['optional'] = true;

            return $inner;
        }

        return ['type' => 'unknown', 'optional' => true]; // @codeCoverageIgnore
    }

    /**
     * Analyze $this->whenLoaded('relation') or $this->whenLoaded('relation', value, default).
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenLoaded(MethodCall $call): array
    {
        $args = $call->getArgs();

        // $this->whenLoaded('relation', value) — use value for type
        if (count($args) >= 2) {
            $inner = $this->analyzeValueExpression($args[1]->value);
            $inner['optional'] = true;

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

        return ['type' => 'unknown', 'optional' => true]; // @codeCoverageIgnore
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
     */
    protected function resolveArrayOrClosureToProperties(Expr $expr, bool $optional): ResourceAnalysis
    {
        if ($expr instanceof Array_) {
            return $this->extractPropertiesFromArray($expr, $optional);
        }

        $resolved = $this->resolveClosureReturnExpression($expr);

        if ($resolved instanceof Array_) {
            return $this->extractPropertiesFromArray($resolved, $optional);
        }

        return new ResourceAnalysis; // @codeCoverageIgnore
    }

    /**
     * Analyze a static method call like EnumResource::make() or SomeResource::make/collection().
     *
     * @return ValueExpressionResult
     */
    protected function analyzeStaticCall(StaticCall $call): array
    {
        $className = $this->resolveStaticCallClassName($call);
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($className === null || $methodName === null) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
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
                'type' => $resourceName.'[]',
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        return ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Analyze `new SomeResource(...)` — resolve as a nested resource.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeNewResource(New_ $expr): array
    {
        if (! $expr->class instanceof Name) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
        }

        $className = $expr->class->toString();

        // new EnumResource($this->prop)
        if ($this->isEnumResourceClass($className)) {
            $args = $expr->getArgs();

            if (count($args) >= 1) {
                return $this->resolveEnumFromPropertyArg($args[0]->value)
                    ?? ['type' => 'unknown', 'optional' => false];
            }

            return ['type' => 'unknown', 'optional' => false];
        }

        if (! $this->isResourceClass($className)) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
        }

        $resourceName = class_basename($className);
        $optional = $this->hasConditionalNewArgument($expr);

        /** @var class-string $className */
        return [
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
        if ($call->isFirstClassCallable()) {
            return ['type' => 'unknown', 'optional' => false];
        }

        $args = $call->getArgs();

        if (count($args) < 1) {
            return ['type' => 'unknown', 'optional' => false];
        }

        return $this->resolveEnumFromPropertyArg($args[0]->value)
            ?? ['type' => 'unknown', 'optional' => false];
    }

    /**
     * Resolve an enum type from a $this->property expression (shared by EnumResource::make and new EnumResource).
     *
     * @return ValueExpressionResult|null
     */
    protected function resolveEnumFromPropertyArg(Expr $argExpr): ?array
    {
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
            'type' => $info['type'],
            'optional' => false,
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
        /** @var PropertyFetch $expr */
        $propName = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($propName === null) {
            return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
        }

        // 0. Handle $this->collection in ResourceCollection subclasses
        if ($propName === 'collection' && $this->isResourceCollection()) {
            return $this->analyzeCollectionProperty();
        }

        // 1. Try model attributes (DB columns, accessors, mutators)
        $info = $this->resolveModelAttributeTypeInfo($propName);

        if ($info['type'] !== 'unknown') {
            $result = ['type' => $info['type'], 'optional' => false];

            if ($info['enumFqcn'] !== null) {
                $result['directEnumFqcn'] = $info['enumFqcn'];
            }

            return $result;
        }

        // 2. Fall back to model relations
        $relationInfo = $this->resolveModelRelationTypeInfo($propName);

        if ($relationInfo['type'] !== 'unknown') {
            $result = ['type' => $relationInfo['type'], 'optional' => false];

            if ($relationInfo['modelFqcn'] !== null) {
                $result['modelFqcn'] = $relationInfo['modelFqcn'];
            }

            return $result;
        }

        return ['type' => 'unknown', 'optional' => false];
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

    /**
     * Parse PHP source and resolve fully qualified names via AST traversal.
     *
     * @return array<Node>
     */
    protected function parseAndResolveAst(string $source): array
    {
        $parser = (new ParserFactory)->createForNewestSupportedVersion();
        /** @var list<Node\Stmt> $stmts */
        $stmts = $parser->parse($source);

        $traverser = new NodeTraverser;
        $traverser->addVisitor(new NameResolver);

        return $traverser->traverse($stmts);
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
        $singular = $this->resolveSingularResourceClass();

        if ($singular === null) {
            return ['type' => 'unknown', 'optional' => false];
        }

        return [
            'type' => class_basename($singular).'[]',
            'optional' => false,
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
        $result = ['type' => 'unknown', 'optional' => false];

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

        return ['type' => '{ '.implode('; ', $parts).' }', 'optional' => false];
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
    protected function analyzeWrappedResourceProperty(PropertyFetch $expr): array
    {
        $innerProp = $expr->name instanceof Identifier ? $expr->name->toString() : null;

        if ($innerProp === null) {
            return ['type' => 'unknown', 'optional' => false];
        }

        if ($innerProp === 'name') {
            return ['type' => 'string', 'optional' => false];
        }

        if ($innerProp === 'value') {
            return ['type' => $this->resolveEnumValueBackingType(), 'optional' => false];
        }

        return ['type' => 'unknown', 'optional' => false];
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
            $r = new \ReflectionEnum($wrappedClass);
            $backingType = $r->getBackingType();

            if ($backingType !== null) {
                return $backingType->getName() === 'string' ? 'string' : 'number';
            }
        }

        return 'string | number';
    }

    /**
     * Resolve the FQCN of the type wrapped by this resource via the `@var` docblock on
     *
     * the `$resource` property (e.g. `/** @var MediaType|null *\/`).
     *
     * Short names are resolved to FQCNs using the file's use-statement map.
     */
    protected function resolveWrappedClass(): ?string
    {
        if (! $this->resourceReflection->hasProperty('resource')) {
            return null;
        }

        $docComment = $this->resourceReflection->getProperty('resource')->getDocComment();

        if ($docComment === false || ! preg_match('/@var\s+([\w\\\\]+)/', $docComment, $m)) {
            return null;
        }

        $shortName = $m[1];

        foreach ($this->resolveUseStatements() as $alias => $fqcn) {
            if ($alias === $shortName && (class_exists($fqcn) || enum_exists($fqcn))) {
                return $fqcn;
            }
        }

        if (class_exists($shortName) || enum_exists($shortName)) {
            return $shortName;
        }

        return null;
    }

    /**
     * Parse the use statements from the resource's source file.
     *
     * @return array<string, string> alias => fully-qualified class name
     */
    protected function resolveUseStatements(): array
    {
        $filePath = (string) $this->resourceReflection->getFileName();
        $source = (string) file_get_contents($filePath);
        $stmts = $this->parseAndResolveAst($source);

        $finder = new NodeFinder;
        /** @var array<string, string> */
        $map = [];

        foreach ($finder->find($stmts, fn (Node $n) => $n instanceof Use_) as $useNode) {
            if (! $useNode instanceof Use_) {
                continue; // @codeCoverageIgnore
            }

            foreach ($useNode->uses as $use) {
                $alias = $use->alias !== null ? $use->alias->toString() : $use->name->getLast();
                $map[$alias] = $use->name->toString();
            }
        }

        return $map;
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
            $tsInfo = LaravelTsPublish::methodReturnedTypes($this->resourceReflection, $methodName);

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
            $tsInfo = LaravelTsPublish::methodReturnedTypes(new ReflectionClass($wrappedClass), $methodName);

            if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                return [
                    ...$tsInfo,
                    'optional' => false,
                ];
            }
        }

        return ['type' => 'unknown', 'optional' => false]; // @codeCoverageIgnore
    }
}
