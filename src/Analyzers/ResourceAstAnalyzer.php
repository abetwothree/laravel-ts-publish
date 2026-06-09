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
use Illuminate\Support\Facades\Config;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BinaryOp;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Cast\Array_ as CastArray_;
use PhpParser\Node\Expr\Cast\Bool_ as CastBool;
use PhpParser\Node\Expr\Cast\Double as CastDouble;
use PhpParser\Node\Expr\Cast\Int_ as CastInt;
use PhpParser\Node\Expr\Cast\String_ as CastString;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Ternary;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Expr\UnaryPlus;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\Scalar\Float_;
use PhpParser\Node\Scalar\Int_;
use PhpParser\Node\Scalar\InterpolatedString;
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
use ReflectionMethod;

/**
 * Analyzes a JsonResource's toArray() method body to extract property names,
 * types, and conditional (optional) markers using AST parsing.
 *
 * @phpstan-import-type ResourcePropertyInfoList from ResourceAnalysis
 * @phpstan-import-type ClassMapType from ResourceAnalysis
 * @phpstan-import-type ImportMapType from ResourceAnalysis
 * @phpstan-import-type InlineEnumFqcnsMap from ResourceAnalysis
 * @phpstan-import-type InlineModelFqcnsMap from ResourceAnalysis
 * @phpstan-import-type MultiEnumFqcnsMap from ResourceAnalysis
 *
 * @phpstan-type ValueExpressionResult = array{
 *      type: string,
 *      optional: bool,
 *      enumFqcn?: class-string,
 *      directEnumFqcn?: class-string,
 *      resourceFqcn?: class-string,
 *      modelFqcn?: class-string,
 *      embeddedEnumFqcns?: list<class-string>,
 *      embeddedEnumResourceFqcns?: list<class-string>,
 *      embeddedModelFqcns?: list<class-string>,
 *      embeddedResourceFqcns?: list<class-string>,
 *      multiEnumResourceFqcns?: list<class-string>
 * }
 * @phpstan-type ClosureAnnotationResult = array{
 *      type: string,
 *      directEnumFqcn?: class-string,
 *      modelFqcn?: class-string
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
     * Maps closure parameter variable names to bound expressions extracted from the
     * surrounding `when()` condition. For example, `$this->when($this->status !== null,
     * function ($status) { ... })` binds `'status'` → the `$this->status` PropertyFetch
     * node, so that `EnumResource::make($status)` inside the closure can be resolved
     * to the same type as `EnumResource::make($this->status)`.
     *
     * @var array<string, Expr>
     */
    protected array $closureParamExprBindings = [];

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
            if ($this->isResourceCollection()) {
                return $this->buildCollectionDelegatedAnalysis();
            }

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
            if ($this->resourceReflection->hasMethod('toArray')) {
                $this->applyTsResourceCastsFromMethod($this->resourceReflection->getMethod('toArray'), $branchAnalysis);
            }

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
        /** @var InlineEnumFqcnsMap $inlineEnumFqcns */
        $inlineEnumFqcns = [];
        /** @var InlineModelFqcnsMap $inlineModelFqcns */
        $inlineModelFqcns = [];
        /** @var MultiEnumFqcnsMap $multiEnumResourceFqcns */
        $multiEnumResourceFqcns = [];
        /** @var InlineEnumFqcnsMap $inlineEnumResourceFqcns */
        $inlineEnumResourceFqcns = [];

        foreach ($array->items as $item) {
            // Handle ...parent::toArray($request) spread
            if ($item->key === null && $item->unpack && $this->isParentToArrayCall($item->value)) {
                $parentAnalysis = $this->analyzeParentToArray();

                if ($parentAnalysis !== null) {
                    $this->syncAnalysisMaps(
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports,
                        $parentAnalysis, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns,
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
                        $filterAnalysis, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns,
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
                        $spreadAnalysis, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns,
                    );
                }

                continue;
            }

            // Handle ...functionCall() spread (bare trait method calls without $this->)
            if ($item->key === null && $item->unpack && $item->value instanceof FuncCall) {
                /** @var Node $funcCallName */
                $funcCallName = $item->value->name;

                if ($funcCallName instanceof Name) {
                    $funcName = $funcCallName->getLast();

                    if ($this->resourceReflection->hasMethod($funcName)) {
                        $spreadAnalysis = $this->analyzeThisMethodSpread($funcName);

                        if ($spreadAnalysis !== null) {
                            $this->syncAnalysisMaps(
                                $properties, $enumResources, $nestedResources,
                                $directEnumFqcns, $modelFqcns, $customImports,
                                $spreadAnalysis, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                                $inlineEnumResourceFqcns,
                            );
                        }
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
                    $mergeResult, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                    $inlineEnumResourceFqcns,
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
            unset($enumResources[$keyName], $nestedResources[$keyName], $directEnumFqcns[$keyName], $modelFqcns[$keyName], $multiEnumResourceFqcns[$keyName]);

            $properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $result['optional'],
                'description' => '',
            ];

            $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns, $multiEnumResourceFqcns);

            foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
                $inlineEnumFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
                $inlineEnumResourceFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
                $inlineModelFqcns[$keyName][] = $fqcn;
            }

        }

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
            inlineEnumFqcns: $inlineEnumFqcns,
            inlineModelFqcns: $inlineModelFqcns,
            multiEnumResourceFqcns: $multiEnumResourceFqcns,
            inlineEnumResourceFqcns: $inlineEnumResourceFqcns,
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
     * @param  InlineEnumFqcnsMap  $inlineEnumFqcns
     * @param  InlineModelFqcnsMap  $inlineModelFqcns
     * @param  MultiEnumFqcnsMap  $multiEnumResourceFqcns
     * @param  InlineEnumFqcnsMap  $inlineEnumResourceFqcns
     */
    protected function syncAnalysisMaps(
        array &$properties,
        array &$enumResources,
        array &$nestedResources,
        array &$directEnumFqcns,
        array &$modelFqcns,
        array &$customImports,
        ResourceAnalysis $source,
        array &$inlineEnumFqcns = [],
        array &$inlineModelFqcns = [],
        array &$multiEnumResourceFqcns = [],
        array &$inlineEnumResourceFqcns = [],
    ): void {
        $properties = [...$properties, ...$source->properties];
        $enumResources = [...$enumResources, ...$source->enumResources];
        $nestedResources = [...$nestedResources, ...$source->nestedResources];
        $directEnumFqcns = [...$directEnumFqcns, ...$source->directEnumFqcns];
        $modelFqcns = [...$modelFqcns, ...$source->modelFqcns];
        $multiEnumResourceFqcns = [...$multiEnumResourceFqcns, ...$source->multiEnumResourceFqcns];

        foreach ($source->customImports as $path => $types) {
            $customImports[$path] = [...($customImports[$path] ?? []), ...$types];
        }

        foreach ($source->inlineEnumFqcns as $propName => $fqcns) {
            $inlineEnumFqcns[$propName] = array_values(array_unique(
                [...($inlineEnumFqcns[$propName] ?? []), ...$fqcns]
            ));
        }

        foreach ($source->inlineModelFqcns as $propName => $fqcns) {
            $inlineModelFqcns[$propName] = array_values(array_unique(
                [...($inlineModelFqcns[$propName] ?? []), ...$fqcns]
            ));
        }

        foreach ($source->inlineEnumResourceFqcns as $propName => $fqcns) {
            $inlineEnumResourceFqcns[$propName] = array_values(array_unique(
                [...($inlineEnumResourceFqcns[$propName] ?? []), ...$fqcns]
            ));
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

        // PHP cast operators — type is determined entirely by the cast, not the inner expression.
        // (bool), (int), (float)/(double), (string), (array) → map directly to TS primitives.
        if ($expr instanceof CastBool) {
            return ['type' => 'boolean', 'optional' => false];
        }

        if ($expr instanceof CastInt || $expr instanceof CastDouble) {
            return ['type' => 'number', 'optional' => false];
        }

        if ($expr instanceof CastString) {
            return ['type' => 'string', 'optional' => false];
        }

        if ($expr instanceof CastArray_) {
            return ['type' => 'unknown[]', 'optional' => false];
        }

        // Scalar literals — String_, Int_, Float_ nodes appear as ternary branch values,
        // array items, and other inline expressions.
        if ($expr instanceof String_ || $expr instanceof InterpolatedString) {
            return ['type' => 'string', 'optional' => false];
        }

        if ($expr instanceof Int_ || $expr instanceof Float_) {
            return ['type' => 'number', 'optional' => false];
        }

        // Unary numeric operators (-x, +x) always produce a numeric result in PHP.
        // Non-literal operands (e.g. -$variable) are handled optimistically as `number`
        // because the analyzer does not track variable types at this stage.
        if ($expr instanceof UnaryMinus || $expr instanceof UnaryPlus) {
            return ['type' => 'number', 'optional' => false];
        }

        // true / false constants resolve to boolean; null resolves to null.
        if ($expr instanceof ConstFetch) {
            $constName = $expr->name->toLowerString();
            if ($constName === 'null') {
                return ['type' => 'null', 'optional' => false];
            }
            if (in_array($constName, ['true', 'false'], true)) {
                return ['type' => 'boolean', 'optional' => false];
            }
        }

        // Arithmetic binary operations always produce a numeric result.
        // This handles cases like `(int) round(...) / 2` where PHP's operator
        // precedence causes the cast to bind tighter than the division, making
        // the outer AST node a BinaryOp\Div rather than a Cast.
        if ($expr instanceof BinaryOp\Plus
            || $expr instanceof BinaryOp\Minus
            || $expr instanceof BinaryOp\Mul
            || $expr instanceof BinaryOp\Div
            || $expr instanceof BinaryOp\Mod
            || $expr instanceof BinaryOp\Pow
        ) {
            return ['type' => 'number', 'optional' => false];
        }

        // String concatenation always produces a string result.
        if ($expr instanceof BinaryOp\Concat) {
            return ['type' => 'string', 'optional' => false];
        }

        // Null coalescing operator ($left ?? $right) — returns left if non-null, right otherwise.
        // Resolve both sides and return the dominant type; if they match return that type,
        // otherwise build a union of the two distinct types.
        if ($expr instanceof BinaryOp\Coalesce) {
            return $this->analyzeCoalesce($expr);
        }

        // Known PHP built-in function calls — resolve return type from function name.
        // e.g. strtolower() → string, strlen() → number, is_null() → boolean.
        if ($expr instanceof FuncCall && $expr->name instanceof Name) {
            $tsType = $this->resolveKnownFunctionCallType($expr->name->getLast());

            if ($tsType !== null) {
                return ['type' => $tsType, 'optional' => false];
            }
        }

        // Closures / arrow functions — analyze the body first. If that returns unknown,
        // fall back to the return type annotation (e.g. `fn (): ?string => ...` → `string | null`).
        $closureReturns = $this->resolveClosureReturnExpressions($expr);

        if ($closureReturns !== []) {
            $bodyResult = count($closureReturns) === 1
                ? $this->analyzeValueExpression($closureReturns[0])
                : $this->analyzeClosureUnion($closureReturns);

            if ($bodyResult['type'] !== 'unknown') {
                return $bodyResult;
            }

            // Body is unknown — try the return type annotation as a fallback
            $annotationResult = $this->resolveClosureAstReturnType($expr);

            if ($annotationResult !== null) {
                return [...$annotationResult, 'optional' => false];
            }

            return $bodyResult;
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

        // $this->whenNull($this->value, $callback)
        if ($this->isThisMethodCall($expr, 'whenNull')) {
            /** @var MethodCall $expr */
            return $this->analyzeWhenNull($expr);
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

        // SomeResource::collection(...)->resolve() — strip the trailing ->resolve() and
        // delegate to analyzeStaticCall so the resource type is still inferred correctly.
        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'resolve'
            && $expr->var instanceof StaticCall
        ) {
            return $this->analyzeStaticCall($expr->var);
        }

        // $this::staticMethod() — the resource itself is the receiver.
        // Reuse analyzeThisMethodCall which checks: resource methods → wrapped class → @mixin model.
        if ($expr instanceof StaticCall
            && $expr->class instanceof Variable
            && $expr->class->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeThisMethodCall($expr->name->toString());
        }

        // $this->resource::staticMethod() — delegate to the wrapped/model class.
        // Must come before the closure-context PropertyFetch handler below.
        if ($expr instanceof StaticCall
            && $expr->class instanceof PropertyFetch
            && $expr->class->var instanceof Variable
            && $expr->class->var->name === 'this'
            && $expr->class->name instanceof Identifier
            && $expr->class->name->toString() === 'resource'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeStaticMethodOnResource($expr->name->toString());
        }

        // $this->relation::staticMethod() or $this->resource->relation::staticMethod()
        // inside a whenLoaded closure — delegate to the related model's method resolver.
        if ($expr instanceof StaticCall
            && $expr->class instanceof PropertyFetch
            && $expr->name instanceof Identifier
        ) {
            /** @var class-string<Model>|null $closureModelClass */
            $closureModelClass = $this->closureRelationModelClass;

            if ($closureModelClass !== null) {
                return $this->analyzeRelatedModelMethodCall($expr->name->toString());
            }
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

        // $this->anyProp?->method() or $this->resource->anyProp?->method() — nullsafe method chain.
        // e.g. $this->categoryRel?->isFirst(), $this->resource->categoryRel?->isActive()
        if ($expr instanceof NullsafeMethodCall) {
            return $this->analyzeMethodChain($expr);
        }

        // $this->anyProp?->subProp or $this->resource->anyProp?->subProp — nullsafe property chain
        // e.g. $this->user?->role, $this->user?->profile?->bio, $this->resource->user?->profile
        if ($expr instanceof NullsafePropertyFetch) {
            return $this->analyzePropertyChain($expr);
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

            // Final fallback: general chain traversal (e.g. $this->resource->name when the
            // specific enum/model-wrapped handlers cannot resolve it).
            if ($info['type'] === 'unknown') {
                $info = $this->analyzePropertyChain($expr);
            }

            return $info;
        }

        // $this->anyProp->subProp->deepProp — plain (non-nullsafe) PropertyFetch chains of
        // 3 or more levels rooted at $this (e.g. `$this->resource->user->role` inside a
        // whenLoaded closure). The 2-deep handler above is skipped because $expr->var is not
        // a direct $this->prop, so we fall through to the general chain traversal here.
        if ($expr instanceof PropertyFetch) {
            $info = $this->analyzePropertyChain($expr);

            if ($info['type'] !== 'unknown') {
                return $info;
            }
        }

        // $this->anyProp->method() — e.g. $this->resource->extensions() on a backed enum or model
        if ($expr instanceof MethodCall
            && $this->isThisPropertyFetch($expr->var)
            && $expr->name instanceof Identifier
        ) {
            $info = $this->analyzeWrappedResourceMethodCall($expr);

            // When inside a whenLoaded closure and the wrapped-class resolution returned unknown,
            // try resolving the method against the related model (e.g. $this->user->nameTitled()).
            /** @var class-string<Model>|null $closureModelClass */
            $closureModelClass = $this->closureRelationModelClass;

            if ($info['type'] === 'unknown' && $closureModelClass !== null) {
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

        /** @var class-string<Model>|null $closureModelClass */
        $closureModelClass = $this->closureRelationModelClass;

        // $variable->property — resolve against the related model in a whenLoaded closure context
        if ($closureModelClass !== null
            && $expr instanceof PropertyFetch
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeRelatedModelProperty($expr->name->toString());
        }

        // $variable->map(fn (TypedClass $item) => [...]) — resolve using the inner closure's
        // typed first parameter. Does not require a pre-existing closureRelationModelClass
        // because the element type is read directly from the closure's type hint.
        if ($expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'map'
            && $expr->getArgs() !== []
        ) {
            $mapResult = $this->analyzeVariableMapCall($expr);

            if ($mapResult !== null) {
                return $mapResult;
            }
        }

        // $variable->pluck('field') — resolve to an array of the field's type
        if ($this->closureRelationModelClass !== null
            && $expr instanceof MethodCall
            && $expr->var instanceof Variable
            && is_string($expr->var->name)
            && $expr->var->name !== 'this'
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'pluck'
        ) {
            return $this->analyzeVariablePluckCall($expr);
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

        // Ternary / Elvis operator: $cond ? $if : $else  or  $cond ?: $else
        if ($expr instanceof Ternary) {
            return $this->analyzeTernary($expr);
        }

        // Bare closure-parameter variable bound via bindClosureParamsFromCondition().
        // E.g. `$this->when($this->status, fn ($status) => $status)` — the closure body
        // returns just `$status`, which is bound to `$this->status`, so we resolve the
        // bound expression to get the correct type (e.g. `OrderStatusType`).
        if ($expr instanceof Variable && is_string($expr->name)) {
            $boundExpr = $this->closureParamExprBindings[$expr->name] ?? null;

            if ($boundExpr !== null) {
                return $this->analyzeValueExpression($boundExpr);
            }
        }

        return $result;
    }

    /**
     * Resolve a PHP built-in function name to its TypeScript return type using reflection.
     *
     * Returns null when the function is unknown, has no declared return type, returns a
     * class/interface type, or when the resolved TS type is unknown.
     */
    private function resolveKnownFunctionCallType(string $name): ?string
    {
        $tsInfo = LaravelTsPublish::nativePhpFunctionReturnedTypes($name);

        return ! str_contains($tsInfo['type'], 'unknown') ? $tsInfo['type'] : null;
    }

    /**
     * Analyze a null-coalescing expression (`$left ?? $right`).
     *
     * Resolves both operands and returns the dominant type. When both sides resolve to
     * the same type the result is that type (e.g. `string ?? string` → `string`). When
     * they differ, a union of the two distinct types is returned (e.g. `string ?? number`
     * → `string | number`). A `null` type on the left is treated as unknown because the
     * coalesce operator guarantees the fallback is used instead.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeCoalesce(BinaryOp\Coalesce $expr): array
    {
        $leftResult = $this->analyzeValueExpression($expr->left);
        $rightResult = $this->analyzeValueExpression($expr->right);

        $leftType = $leftResult['type'];
        $rightType = $rightResult['type'];

        // Strip a `| null` suffix from the left side — the right side acts as the fallback,
        // so null is never the final result when a non-null fallback is provided.
        $leftType = trim(str_replace('| null', '', $leftType));
        $leftType = trim(str_replace('null |', '', $leftType));

        if ($leftType === 'unknown' || $leftType === '') {
            return ['type' => $rightType, 'optional' => false];
        }

        if ($rightType === 'unknown') {
            return ['type' => $leftType, 'optional' => false];
        }

        if ($leftType === $rightType) {
            return ['type' => $leftType, 'optional' => false];
        }

        return ['type' => $leftType.' | '.$rightType, 'optional' => false];
    }

    /**
     * Analyze a ternary or Elvis expression.
     *
     * Regular ternary (`$cond ? $if : $else`): both branches are analyzed and their
     * types are merged with union semantics via analyzeClosureUnion. If one branch is
     * a null literal, the resulting type string includes `| null` (e.g. `StatusType | null`).
     *
     * Elvis (`$cond ?: $else`, where `$if === null`): the truthy value is `$cond` itself,
     * and `$else` is the non-null fallback. Both sides are analyzed and unioned.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeTernary(Ternary $expr): array
    {
        // Elvis: $cond ?: $else — the truthy branch IS the condition expression
        if ($expr->if === null) {
            return $this->analyzeClosureUnion([$expr->cond, $expr->else]);
        }

        // Regular ternary: $cond ? $if : $else
        return $this->analyzeClosureUnion([$expr->if, $expr->else]);
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

            // When the condition contains $this->propName, bind the closure's first
            // parameter to that expression so that `EnumResource::make($param)` and
            // similar calls inside the closure can be resolved correctly.
            $previousBindings = $this->closureParamExprBindings;
            $this->bindClosureParamsFromCondition($args[0]->value, $valueExpr);

            // Resolve the type from the value expression
            $inner = $this->analyzeValueExpression($valueExpr);
            $inner['optional'] = true;

            $this->closureParamExprBindings = $previousBindings;

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
     * Analyze $this->whenNotNull($this->value, $callback) — resolve the callback expression type.
     *
     * whenNotNull passes the non-null value of $args[0] to the callback $args[1].
     * We analyze the callback (args[1]) for the TypeScript type, and bind the
     * closure param to the condition expression (args[0]) so inner calls resolve correctly.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenNotNull(MethodCall $call): array
    {
        return $this->analyzeWhenPossiblyNull($call);
    }

    /**
     * Analyze $this->whenNull($this->value, $callback) — resolve the callback expression type.
     *
     * whenNull passes null to the callback when the value is null. We analyze args[1] (the
     * callback) for the TypeScript type. No closure param binding is needed because null
     * is passed — there is no meaningful expression to bind the param to.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenNull(MethodCall $call): array
    {
        return $this->analyzeWhenPossiblyNull($call);
    }

    /**
     * Analyze $this->whenNotNull(...) or $this->whenNull(...) — shared logic for analyzing the callback and binding the closure param.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeWhenPossiblyNull(MethodCall $call): array
    {
        $result = $this->unknownResult();
        $args = $call->getArgs();

        if (count($args) === 1) {
            $inner = $this->analyzeValueExpression($args[0]->value);
            $inner['optional'] = true;

            return $inner;
        }

        if (count($args) >= 2) {
            $valueExpr = $args[1]->value;
            $previousBindings = $this->closureParamExprBindings;

            $this->bindClosureParamsFromCondition($args[0]->value, $valueExpr);
            $inner = $this->analyzeValueExpression($valueExpr);
            $inner['optional'] = true;

            $this->closureParamExprBindings = $previousBindings;

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

        // Resolve `self` and `static` to the resource's own FQCN so that
        // self::make(), self::collection(), and static::*() calls are treated
        // identically to ClassName::make() / ClassName::collection() calls.
        if ($className === 'self' || $className === 'static') {
            $className = $this->resourceReflection->getName();
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

        // Resolve `self` and `static` to the resource's own FQCN so that
        // `new self(...)` is treated identically to `new ClassName(...)`.
        if ($className === 'self' || $className === 'static') {
            $className = $this->resourceReflection->getName();
        }

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
     * Resolve an enum type from a property-fetch expression (shared by EnumResource::make and new EnumResource).
     *
     * Handles two forms:
     * - `$this->property` — resolved against the resource's own backing model.
     * - `$variable->property` — resolved against `$closureRelationModelClass` when inside a
     *   whenLoaded() closure (e.g. `fn ($user) => EnumResource::make($user->role)`).
     *
     * @return ValueExpressionResult|null
     */
    protected function resolveEnumFromPropertyArg(Expr $argExpr): ?array
    {
        $result = $this->unknownResult();

        if (! $this->isThisPropertyFetch($argExpr)) {
            // Handle bare $variable that is a closure parameter bound to $this->prop
            // via a when() condition (e.g. `EnumResource::make($status)` where
            // `$status` was bound to `$this->status` from the condition).
            if ($argExpr instanceof Variable && is_string($argExpr->name)) {
                $boundExpr = $this->closureParamExprBindings[$argExpr->name] ?? null;

                if ($boundExpr !== null) {
                    return $this->resolveEnumFromPropertyArg($boundExpr);
                }
            }

            // Handle $variable->property inside a whenLoaded closure.
            if (
                $argExpr instanceof PropertyFetch
                && $argExpr->var instanceof Variable
                && $argExpr->name instanceof Identifier
                && $this->closureRelationModelClass !== null
            ) {
                $propName = $argExpr->name->toString();
                $tsInfo = resolve(ModelAttributeResolver::class)->resolveAttribute($this->closureRelationModelClass, $propName);

                /** @var class-string|null $enumFqcn */
                $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

                if ($enumFqcn === null) {
                    return null;
                }

                // Use toTsType on the enum FQCN directly so we get the
                // pure enum TS type ('RoleType') without any nullable suffix that
                // appendNullable may have appended based on the DB column definition.
                $enumTsInfo = LaravelTsPublish::toTsType($enumFqcn);

                return [
                    ...$result,
                    'type' => $enumTsInfo['type'],
                    'enumFqcn' => $enumFqcn,
                ];
            }

            // Handle $this->resource->property — semantically equivalent to $this->property.
            // In a Laravel Resource, $this->resource is the underlying model instance,
            // so `$this->resource->status` accesses the same attribute as `$this->status`.
            // AST shape: PropertyFetch(var: PropertyFetch(var: Variable('this'), name: 'resource'), name: 'propName')
            if (
                $argExpr instanceof PropertyFetch
                && $argExpr->var instanceof PropertyFetch
                && $this->isThisPropertyFetch($argExpr->var)
                && $argExpr->var->name instanceof Identifier
                && $argExpr->var->name->toString() === 'resource'
                && $argExpr->name instanceof Identifier
            ) {
                $propName = $argExpr->name->toString();
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
        /** @var InlineEnumFqcnsMap $inlineEnumFqcns */
        $inlineEnumFqcns = [];
        /** @var InlineModelFqcnsMap $inlineModelFqcns */
        $inlineModelFqcns = [];
        /** @var MultiEnumFqcnsMap $multiEnumResourceFqcns */
        $multiEnumResourceFqcns = [];
        /** @var InlineEnumFqcnsMap $inlineEnumResourceFqcns */
        $inlineEnumResourceFqcns = [];

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

            $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns, $multiEnumResourceFqcns);

            foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
                $inlineEnumFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
                $inlineEnumResourceFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
                $inlineModelFqcns[$keyName][] = $fqcn;
            }
        }

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
            inlineEnumFqcns: $inlineEnumFqcns,
            inlineModelFqcns: $inlineModelFqcns,
            multiEnumResourceFqcns: $multiEnumResourceFqcns,
            inlineEnumResourceFqcns: $inlineEnumResourceFqcns,
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
        $this->applyTsResourceCastsFromMethod($method, $analysis);

        return $analysis;
    }

    /**
     * Decompose a property-fetch expression rooted at `$this` into an ordered chain of
     * `{name: string, nullable: bool}` steps. Returns null if the root is not `$this`.
     *
     * `nullable` is true when the access operator for that step is `?->` (NullsafePropertyFetch)
     * meaning the chain returns null if the receiver is null.
     *
     * Example: `$this->resource->user?->profile?->bio`
     *   → [{name:'resource',nullable:false},{name:'user',nullable:false},{name:'profile',nullable:true},{name:'bio',nullable:true}]
     *
     * @return list<array{name: string, nullable: bool}>|null
     */
    private function decomposePropertyChain(Expr $expr): ?array
    {
        /** @var list<array{name: string, nullable: bool}> $chain */
        $chain = [];
        $current = $expr;

        while ($current instanceof PropertyFetch || $current instanceof NullsafePropertyFetch) {
            if (! $current->name instanceof Identifier) {
                return null;
            }

            $chain[] = [
                'name' => $current->name->toString(),
                'nullable' => $current instanceof NullsafePropertyFetch,
            ];

            $current = $current->var;
        }

        // Root must be $this
        if (! $current instanceof Variable || $current->name !== 'this') {
            return null;
        }

        return array_reverse($chain);
    }

    /**
     * Analyze a property-fetch chain rooted at `$this` by traversing each relation and
     * attribute step until the final property is resolved.
     *
     * Handles both plain `->` and nullsafe `?->` operators, or any mix of the two.
     * If any step in the chain uses `?->`, `| null` is appended to the resolved type.
     *
     * Entry points:
     * - NullsafePropertyFetch chains (e.g. `$this->user?->role`)
     * - Plain PropertyFetch chains of any depth (e.g. `$this->resource->user->role`)
     * - 2-deep chains like `$this->resource->name` as a final fallback after the specific
     *   enum/model-wrapped handlers
     *
     * The starting model is `$this->closureRelationModelClass` when inside a whenLoaded
     * closure, or `$this->modelClass` otherwise.
     *
     * @return ValueExpressionResult
     */
    private function analyzePropertyChain(Expr $expr): array
    {
        $chain = $this->decomposePropertyChain($expr);

        if ($chain === null || $chain === []) {
            return $this->unknownResult();
        }

        /** @var class-string<Model>|null $currentModel */
        $currentModel = $this->closureRelationModelClass ?? $this->modelClass;

        if ($currentModel === null) {
            return $this->unknownResult();
        }

        $resolver = resolve(ModelAttributeResolver::class);

        // Skip the `$this->resource` wrapper property when it is not a real model relation
        if ($chain[0]['name'] === 'resource') {
            $check = $resolver->resolveRelation($currentModel, 'resource');

            if ($check['type'] === 'unknown') {
                array_shift($chain);
            }
        }

        if ($chain === []) {
            return $this->unknownResult();
        }

        // Whether any step in the chain uses ?-> (making the whole expression nullable)
        $hasNullable = array_any($chain, fn (array $step): bool => $step['nullable']);

        // Traverse all intermediate steps, updating $currentModel to the related model at each step
        $count = count($chain);

        // When inside a whenLoaded closure, the first chain step may be the resource's proxy to the
        // already-loaded relation model (e.g. `$this->user` in `whenLoaded('user', fn() => $this->user?->name)`).
        // If it doesn't resolve as a relation on closureRelationModelClass, skip it.
        $startIndex = 0;

        if ($this->closureRelationModelClass !== null && $count >= 2) {
            $firstRelation = $resolver->resolveRelation($currentModel, $chain[0]['name']);

            if ($firstRelation['type'] === 'unknown') {
                $startIndex = 1;
            }
        }

        for ($i = $startIndex; $i < $count - 1; $i++) {
            $relationInfo = $resolver->resolveRelation($currentModel, $chain[$i]['name']);

            if ($relationInfo['type'] === 'unknown' || $relationInfo['modelFqcn'] === null) {
                return $this->unknownResult();
            }

            $currentModel = $relationInfo['modelFqcn'];
        }

        // Resolve the final step as an attribute first, then fall back to relation
        $lastStep = $chain[$count - 1];
        $tsInfo = $resolver->resolveAttribute($currentModel, $lastStep['name']);

        if ($tsInfo['type'] === 'unknown') {
            // Fallback: the final step might itself be a relation (e.g. $this->user?->profile)
            $relationInfo = $resolver->resolveRelation($currentModel, $lastStep['name']);

            if ($relationInfo['type'] === 'unknown') {
                return $this->unknownResult();
            }

            $type = $hasNullable && ! str_ends_with($relationInfo['type'], ' | null')
                ? $relationInfo['type'].' | null'
                : $relationInfo['type'];

            /** @var ValueExpressionResult $result */
            $result = ['type' => $type, 'optional' => false];

            if ($relationInfo['modelFqcn'] !== null) {
                $result['modelFqcn'] = $relationInfo['modelFqcn'];
            }

            return $result;
        }

        $type = $hasNullable && ! str_ends_with($tsInfo['type'], ' | null')
            ? $tsInfo['type'].' | null'
            : $tsInfo['type'];

        /** @var ValueExpressionResult $result */
        $result = ['type' => $type, 'optional' => false];

        /** @var class-string|null $enumFqcn */
        $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;

        if ($enumFqcn !== null) {
            $result['directEnumFqcn'] = $enumFqcn;
        }

        return $result;
    }

    /**
     * Analyze a nullsafe method-call chain rooted at `$this`, traversing relations to
     * find the terminal model and resolving the method's return type on it.
     *
     * Handles patterns like:
     * - `$this->categoryRel?->isFirst()` inside a `whenLoaded('categoryRel', ...)` closure
     * - `$this->resource->categoryRel?->isActive()` (resource wrapper stripped)
     * - `$this->user?->profile?->someMethod()` outside a closure
     *
     * Because the operator is `?->`, the result is always made nullable.
     *
     * @return ValueExpressionResult
     */
    private function analyzeMethodChain(NullsafeMethodCall $call): array
    {
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($methodName === null) {
            return $this->unknownResult();
        }

        $chain = $this->decomposePropertyChain($call->var);

        if ($chain === null || $chain === []) {
            return $this->unknownResult();
        }

        /** @var class-string<Model>|null $currentModel */
        $currentModel = $this->closureRelationModelClass ?? $this->modelClass;

        if ($currentModel === null) {
            return $this->unknownResult();
        }

        $resolver = resolve(ModelAttributeResolver::class);

        // Skip the `$this->resource` wrapper property when it is not a real model relation
        if ($chain[0]['name'] === 'resource') {
            $check = $resolver->resolveRelation($currentModel, 'resource');

            if ($check['type'] === 'unknown') {
                array_shift($chain);
            }
        }

        if ($chain === []) {
            return $this->unknownResult();
        }

        $count = count($chain);

        // When inside a whenLoaded closure, the first chain step may be the resource's proxy to the
        // already-loaded relation model (e.g. `$this->categoryRel` in
        // `whenLoaded('categoryRel', fn() => $this->categoryRel?->isFirst())`).
        // If it doesn't resolve as a relation on closureRelationModelClass, skip it.
        $startIndex = 0;

        if ($this->closureRelationModelClass !== null) {
            $firstRelation = $resolver->resolveRelation($currentModel, $chain[0]['name']);

            if ($firstRelation['type'] === 'unknown') {
                $startIndex = 1;
            }
        }

        // Traverse all intermediate relation steps, updating $currentModel at each step
        for ($i = $startIndex; $i < $count - 1; $i++) {
            $relationInfo = $resolver->resolveRelation($currentModel, $chain[$i]['name']);

            if ($relationInfo['type'] === 'unknown' || $relationInfo['modelFqcn'] === null) {
                return $this->unknownResult();
            }

            /** @var class-string<Model> $relatedModel */
            $relatedModel = $relationInfo['modelFqcn'];
            $currentModel = $relatedModel;
        }

        // The last chain step is the relation whose methods we are calling on
        // (e.g., `$this->categoryRel` in `$this->categoryRel?->isFirst()`).
        // Traverse it as a relation to get the terminal model — unless it was the skipped proxy.
        if ($startIndex <= $count - 1) {
            $lastStep = $chain[$count - 1];
            $relationInfo = $resolver->resolveRelation($currentModel, $lastStep['name']);

            if ($relationInfo['type'] !== 'unknown' && $relationInfo['modelFqcn'] !== null) {
                /** @var class-string<Model> $relatedModel */
                $relatedModel = $relationInfo['modelFqcn'];
                $currentModel = $relatedModel;
            }
        }

        $tsInfo = $resolver->resolveMethodReturnType($currentModel, $methodName);

        if ($tsInfo['type'] === '' || $tsInfo['type'] === 'unknown') {
            return $this->unknownResult();
        }

        // NullsafeMethodCall always produces a nullable result
        $type = str_ends_with($tsInfo['type'], ' | null')
            ? $tsInfo['type']
            : $tsInfo['type'].' | null';

        return ['type' => $type, 'optional' => false];
    }

    /**
     * If an arrow function or closure has a return type annotation, resolve it to a
     * ClosureAnnotationResult (type string + optional FQCN metadata). Returns null if the
     * annotation is absent, is a complex union/intersection type, or maps to a non-useful
     * type (void, mixed, never) or an unresolvable class.
     *
     * Used by analyzeValueExpression() as a fallback when body analysis returns unknown,
     * e.g. for `fn (): ?string => someUnresolvableExpression()` or
     * `fn (): Status => someUnresolvableExpression()`.
     *
     * @return ClosureAnnotationResult|null
     */
    private function resolveClosureAstReturnType(Expr $expr): ?array
    {
        if (! $expr instanceof ArrowFunction && ! $expr instanceof ClosureExpr) {
            return null;
        }

        $returnType = $expr->returnType;

        if ($returnType === null) {
            return null;
        }

        return $this->convertAstTypeNodeToTs($returnType);
    }

    /**
     * Convert a PHP-Parser return-type AST node to a ClosureAnnotationResult (type +
     * optional FQCN metadata). Returns null for union/intersection types, void, never,
     * mixed, or unresolvable class names.
     *
     * For Name nodes, enumFqcns[0] is mapped to directEnumFqcn and classFqcns[0] to
     * modelFqcn so the caller can track the FQCN alongside the type string. Types with
     * customImports (e.g. #[TsType] classes with import paths) return null because that
     * metadata cannot be represented in ValueExpressionResult.
     *
     * For NullableType, metadata from the inner node is preserved while '| null' is
     * appended to the type string.
     *
     * @return ClosureAnnotationResult|null
     */
    private function convertAstTypeNodeToTs(Node $typeNode): ?array
    {
        if ($typeNode instanceof NullableType) {
            $inner = $this->convertAstTypeNodeToTs($typeNode->type);

            if ($inner === null) {
                return null;
            }

            return [...$inner, 'type' => $inner['type'].' | null'];
        }

        if ($typeNode instanceof Identifier) {
            $phpType = $typeNode->toString();

            if (in_array($phpType, ['void', 'never', 'mixed'], true)) {
                return null;
            }

            $tsInfo = LaravelTsPublish::toTsType($phpType);

            return $tsInfo['type'] !== 'unknown' ? ['type' => $tsInfo['type']] : null;
        }

        if ($typeNode instanceof Name) {
            $phpType = $typeNode->toString();
            $tsInfo = LaravelTsPublish::toTsType($phpType);

            if ($tsInfo['type'] === 'unknown') {
                return null;
            }

            // Types with customImports cannot be represented in ValueExpressionResult —
            // return null so the caller falls back to 'unknown' rather than emitting a
            // type that is missing its import registration.
            if ($tsInfo['customImports'] !== []) {
                return null;
            }

            /** @var ClosureAnnotationResult $result */
            $result = ['type' => $tsInfo['type']];

            if ($tsInfo['enumFqcns'] !== []) {
                $result['directEnumFqcn'] = $tsInfo['enumFqcns'][0];
            } elseif ($tsInfo['classFqcns'] !== []) {
                $result['modelFqcn'] = $tsInfo['classFqcns'][0];
            }

            return $result;
        }

        // UnionType / IntersectionType — fall through to body analysis
        return null;
    }

    /**
     * Apply #[TsResourceCasts] attribute overrides declared on a reflection method to
     * the given ResourceAnalysis, updating or injecting property types as directed.
     *
     * Used both by analyzeThisMethodSpread() for trait/helper methods and by analyze()
     * for the toArray() method itself, allowing #[TsResourceCasts] to be placed directly
     * on toArray() as a lightweight override mechanism.
     */
    private function applyTsResourceCastsFromMethod(ReflectionMethod $method, ResourceAnalysis $analysis): void
    {
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
        /** @var InlineEnumFqcnsMap $inlineEnumFqcns */
        $inlineEnumFqcns = [];
        /** @var InlineModelFqcnsMap $inlineModelFqcns */
        $inlineModelFqcns = [];
        /** @var MultiEnumFqcnsMap $multiEnumResourceFqcns */
        $multiEnumResourceFqcns = [];
        /** @var InlineEnumFqcnsMap $inlineEnumResourceFqcns */
        $inlineEnumResourceFqcns = [];

        $this->collectVariableArrayAssignments(
            $stmts, $varName, false,
            $properties, $enumResources, $nestedResources,
            $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
            $inlineEnumResourceFqcns,
        );

        return new ResourceAnalysis(
            $properties,
            $enumResources,
            $nestedResources,
            customImports: $customImports,
            directEnumFqcns: $directEnumFqcns,
            modelFqcns: $modelFqcns,
            inlineEnumFqcns: $inlineEnumFqcns,
            inlineModelFqcns: $inlineModelFqcns,
            multiEnumResourceFqcns: $multiEnumResourceFqcns,
            inlineEnumResourceFqcns: $inlineEnumResourceFqcns,
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
     * @param  InlineEnumFqcnsMap  $inlineEnumFqcns
     * @param  InlineModelFqcnsMap  $inlineModelFqcns
     * @param  MultiEnumFqcnsMap  $multiEnumResourceFqcns
     * @param  InlineEnumFqcnsMap  $inlineEnumResourceFqcns
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
        array &$inlineEnumFqcns,
        array &$inlineModelFqcns,
        array &$multiEnumResourceFqcns = [],
        array &$inlineEnumResourceFqcns = [],
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
                    $baseAnalysis, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                    $inlineEnumResourceFqcns,
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
                    $multiEnumResourceFqcns[$keyName],
                );

                $this->dispatchFqcnResults($keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns, $multiEnumResourceFqcns);

                foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
                    $inlineEnumFqcns[$keyName][] = $fqcn;
                }

                foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
                    $inlineEnumResourceFqcns[$keyName][] = $fqcn;
                }

                foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
                    $inlineModelFqcns[$keyName][] = $fqcn;
                }

                continue;
            }

            // if/elseif/else — recurse with isConditional = true
            if ($stmt instanceof If_) {
                $this->collectVariableArrayAssignments(
                    $stmt->stmts, $varName, true,
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                    $inlineEnumResourceFqcns,
                );

                foreach ($stmt->elseifs as $elseif) {
                    $this->collectVariableArrayAssignments(
                        $elseif->stmts, $varName, true,
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns,
                    );
                }

                if ($stmt->else !== null) {
                    $this->collectVariableArrayAssignments(
                        $stmt->else->stmts, $varName, true,
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns,
                    );
                }
            }

            // Loop bodies — recurse with isConditional = true (loops may execute 0 times)
            if ($stmt instanceof Foreach_ || $stmt instanceof For_
                || $stmt instanceof While_ || $stmt instanceof Do_) {
                $this->collectVariableArrayAssignments(
                    $stmt->stmts, $varName, true,
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                    $inlineEnumResourceFqcns,
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
    protected function parseReturnArrayShape(ReflectionMethod $method): array
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
     * Resolution order:
     * 1. #[Collects] PHP attribute on the class (Laravel 12+)
     * 2. Explicit $collects property default value
     * 3. Naming convention: XCollection → XResource
     *
     * @return class-string<JsonResource>|null
     */
    protected function resolveSingularResourceClass(): ?string
    {
        $collectsAttribute = 'Illuminate\Http\Resources\Attributes\Collects';
        if (class_exists($collectsAttribute)) {
            // Priority 1: #[Collects] attribute (Laravel 12+)
            $collectsAttrs = $this->resourceReflection->getAttributes($collectsAttribute);

            if ($collectsAttrs !== []) {
                $collectsClass = $collectsAttrs[0]->newInstance()->class;

                if (class_exists($collectsClass) && is_a($collectsClass, JsonResource::class, true)) {
                    return $collectsClass;
                }
            }
        }

        // Priority 2: explicit $collects property default value
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
     * Build a ResourceAnalysis for a ResourceCollection subclass that has no toArray() method.
     *
     * Reads the $wrap key to determine the property name (default: 'data').
     * - If $wrap is a non-empty string: generates `{ data: SingularResource[] }` interface shape
     * - If $wrap is null: sets flatTypeAlias so the writer emits `export type X = SingularResource[]`
     */
    protected function buildCollectionDelegatedAnalysis(): ResourceAnalysis
    {
        $singular = $this->resolveSingularResourceClass();

        if ($singular === null) {
            return new ResourceAnalysis;
        }

        // Read the declared $wrap value from this class only (not inherited).
        // $wrap is a static property on JsonResource (default: 'data').
        $wrapKey = 'data';

        if ($this->resourceReflection->hasProperty('wrap')) {
            $wrapProp = $this->resourceReflection->getProperty('wrap');

            if ($wrapProp->getDeclaringClass()->getName() === $this->resourceReflection->getName()) {
                /** @var string|null $wrapKey */
                $wrapKey = $wrapProp->getDefaultValue();
            }
        }

        $singularBaseName = class_basename($singular);

        if ($wrapKey === null || $wrapKey === '') {
            return new ResourceAnalysis(flatTypeAlias: $singularBaseName.'[]', flatTypeAliasFqcn: $singular);
        }

        $key = $wrapKey ? $wrapKey : 'data';

        return new ResourceAnalysis(
            properties: [[
                'name' => $key,
                'type' => $singularBaseName.'[]',
                'optional' => false,
                'description' => '',
            ]],
            nestedResources: [$wrapKey => $singular],
        );
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
            // Try the multi-model accessor path (e.g. Attribute<ModelA|ModelB, never>).
            $modelFqcns = $this->resolveAccessorModelFqcns($propName);

            if ($modelFqcns === []) {
                return $result; // @codeCoverageIgnore
            }

            $keys = $this->extractFilterKeys($call);

            if ($keys === null || $keys === []) {
                return $result; // @codeCoverageIgnore
            }

            $include = $methodName === 'only';

            /** @var list<string> $inlineTypes */
            $inlineTypes = [];
            /** @var list<class-string> $embeddedEnumFqcns */
            $embeddedEnumFqcns = [];
            /** @var list<class-string> $embeddedModelFqcns */
            $embeddedModelFqcns = [];

            foreach ($modelFqcns as $fqcn) {
                $filterResult = $this->resolveFilteredRelationType($fqcn, $keys, $include);

                if ($filterResult['type'] !== 'unknown' && ! in_array($filterResult['type'], $inlineTypes, true)) {
                    $inlineTypes[] = $filterResult['type'];
                    array_push($embeddedEnumFqcns, ...$filterResult['enumFqcns']);
                    array_push($embeddedModelFqcns, ...$filterResult['modelFqcns']);
                }
            }

            if ($inlineTypes === []) {
                return $result; // @codeCoverageIgnore
            }

            $inlineType = implode(' | ', $inlineTypes);

            if ($nullable) {
                $inlineType .= ' | null';
            }

            return [
                ...$result,
                'type' => $inlineType,
                'embeddedEnumFqcns' => array_values(array_unique($embeddedEnumFqcns)),
                'embeddedModelFqcns' => array_values(array_unique($embeddedModelFqcns)),
            ];
        }

        $keys = $this->extractFilterKeys($call);

        if ($keys === null || $keys === []) {
            return $result; // @codeCoverageIgnore
        }

        $include = $methodName === 'only';
        $filterResult = $this->resolveFilteredRelationType($modelFqcn, $keys, $include);
        $inlineType = $filterResult['type'];

        // Wrap in array suffix when the relation is a *-many type (HasMany, BelongsToMany, etc.)
        if (str_ends_with($relationInfo['type'], '[]') && $inlineType !== 'unknown') {
            $inlineType .= '[]';
        }

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
     * @param  MultiEnumFqcnsMap  $multiEnumResourceFqcns
     */
    protected function dispatchFqcnResults(
        string $keyName,
        array $result,
        array &$enumResources,
        array &$directEnumFqcns,
        array &$nestedResources,
        array &$modelFqcns,
        array &$multiEnumResourceFqcns = [],
    ): void {
        if (isset($result['enumFqcn'])) {
            $enumResources[$keyName] = $result['enumFqcn'];
        }

        if (isset($result['directEnumFqcn'])) {
            $directEnumFqcns[$keyName] = $result['directEnumFqcn'];
        }

        if (isset($result['multiEnumResourceFqcns'])) {
            $multiEnumResourceFqcns[$keyName] = $result['multiEnumResourceFqcns'];
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

        $useTolki = Config::boolean('ts-publish.enums.use_tolki_package');

        $parts = array_map(function (array $prop) use ($analysis, $useTolki): string {
            $key = LaravelTsPublish::validJsObjectKey($prop['name']);

            $type = $prop['type'];

            // When Tolki is enabled, rewrite the type for EnumResource-wrapped properties
            // to `AsEnum<typeof X>` to match the top-level enum resource transformer behaviour.
            if ($useTolki && isset($analysis->enumResources[$prop['name']])) {
                $fqcn = $analysis->enumResources[$prop['name']];
                $tsInfo = LaravelTsPublish::toTsType($fqcn);
                $constName = $tsInfo['enums'][0] ?? class_basename($fqcn);
                $nullable = str_contains($type, 'null');
                $type = 'AsEnum<typeof '.$constName.'>'.($nullable ? ' | null' : '');
            }

            return $prop['optional']
                ? "{$key}?: {$type}"
                : "{$key}: {$type}";
        }, $analysis->properties);

        $result = ['type' => '{ '.implode('; ', $parts).' }', 'optional' => false];

        // Propagate import metadata from the inner analysis so that enum, model,
        // and resource FQCNs referenced inside the inline object reach the outer
        // ResourceAnalysis and generate the correct import statements.

        // When Tolki is enabled, enum resources need value imports (const), not type imports.
        // Direct enum accesses always need type imports.
        if ($useTolki) {
            $nestedInlineEnumFqcns = $analysis->inlineEnumFqcns === []
                 ? []
                 : array_merge(...array_values($analysis->inlineEnumFqcns));

            $embeddedEnumFqcns = array_values(array_unique([
                ...array_values($analysis->directEnumFqcns),
                // Propagate any deeply-nested direct enum FQCNs from sub-inline-arrays.
                ...$nestedInlineEnumFqcns,
            ]));

            $enumResourceFqcns = array_values($analysis->enumResources);
            // Propagate any deeply-nested enum resource FQCNs from sub-inline-arrays.
            foreach ($analysis->inlineEnumResourceFqcns as $nestedFqcns) {
                foreach ($nestedFqcns as $fqcn) {
                    $enumResourceFqcns[] = $fqcn;
                }
            }
            $embeddedEnumResourceFqcns = array_values(array_unique($enumResourceFqcns));
        } else {
            // Tolki OFF: all enum FQCNs (both direct and EnumResource) need type imports.
            $embeddedEnumFqcns = array_values(array_unique([
                ...array_values($analysis->directEnumFqcns),
                ...array_values($analysis->enumResources),
                ...array_merge(...array_values($analysis->inlineEnumFqcns)),
                ...array_merge(...array_values($analysis->inlineEnumResourceFqcns)),
            ]));
            $embeddedEnumResourceFqcns = [];
        }

        $embeddedModelFqcns = array_values(array_unique(
            array_values($analysis->modelFqcns),
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
            // Fall back to modelClass for `$this->resource->method()` calls on @mixin-style resources
            // (e.g. `$this->resource->commentsCount()` where commentsCount() lives on the model).
            if ($this->modelClass !== null && method_exists($this->modelClass, $methodName)) {
                /** @var class-string $modelClass */
                $modelClass = $this->modelClass;
                $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($modelClass), $methodName);

                if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                    return [...$tsInfo, 'optional' => false];
                }
            }

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
     * Analyze a static method call on the resource's wrapped class or backing model,
     * originating from a `$this->resource::staticMethod()` expression.
     *
     * Mirrors the logic of analyzeWrappedResourceMethodCall: tries the wrapped class first,
     * then falls back to the @mixin model class. PHP reflection handles static methods
     * identically to instance methods, so no special casing is required here.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeStaticMethodOnResource(string $methodName): array
    {
        $result = $this->unknownResult();
        $wrappedClass = $this->resolveWrappedClass();

        if ($wrappedClass !== null && method_exists($wrappedClass, $methodName)) {
            /** @var class-string $wrappedClass */
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($wrappedClass), $methodName);

            if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                return [...$tsInfo, 'optional' => false];
            }
        }

        if ($this->modelClass !== null && method_exists($this->modelClass, $methodName)) {
            /** @var class-string $modelClass */
            $modelClass = $this->modelClass;
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($modelClass), $methodName);

            if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                return [...$tsInfo, 'optional' => false];
            }
        }

        return $result;
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

        // 3. Fall back to the resource's backing model class (covers @mixin-delegated calls
        //    like `$this->publishable()` on a resource with `@mixin Post`).
        if ($this->modelClass !== null && method_exists($this->modelClass, $methodName)) {
            /** @var class-string $modelClass */
            $modelClass = $this->modelClass;
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($modelClass), $methodName);

            if ($tsInfo['type'] !== '' && $tsInfo['type'] !== 'unknown') {
                return [
                    ...$tsInfo,
                    'optional' => false,
                ];
            }
        }

        return $this->unknownResult();
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
        /** @var MultiEnumFqcnsMap $multiEnumResourceFqcns */
        $multiEnumResourceFqcns = [];

        foreach ($analyses as $analysis) {
            foreach ($analysis->properties as $prop) {
                $propertyMap[$prop['name']][] = $prop;
            }

            $enumResources = [...$enumResources, ...$analysis->enumResources];
            $nestedResources = [...$nestedResources, ...$analysis->nestedResources];
            $directEnumFqcns = [...$directEnumFqcns, ...$analysis->directEnumFqcns];
            $modelFqcns = [...$modelFqcns, ...$analysis->modelFqcns];
            $multiEnumResourceFqcns = [...$multiEnumResourceFqcns, ...$analysis->multiEnumResourceFqcns];

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
            multiEnumResourceFqcns: $multiEnumResourceFqcns,
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
        /** @var list<class-string> $enumResourceFqcns FQCNs from EnumResource::make() / new EnumResource() branches */
        $enumResourceFqcns = [];
        /** @var list<class-string> $enumDirectFqcns FQCNs from direct $this->prop enum-access branches */
        $enumDirectFqcns = [];
        /** @var list<class-string> $embeddedEnumFqcns FQCNs embedded inside nested inline-object types */
        $embeddedEnumFqcns = [];
        /** @var list<class-string> $embeddedModelFqcns */
        $embeddedModelFqcns = [];
        /** @var list<class-string> $embeddedResourceFqcns */
        $embeddedResourceFqcns = [];
        $hasNull = false;

        foreach ($returns as $returnExpr) {
            // Guard-clause null (e.g. `return null;` at the top level of a closure branch)
            // is intercepted here before reaching analyzeValueExpression, so the standalone
            // `null` union member is tracked separately from object-shape branches. Null that
            // appears as an *array value* (e.g. `return ['key' => null]`) is handled inside
            // analyzeValueExpression via the ConstFetch 'null' branch and never reaches here.
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

            // Merge import metadata — track EnumResource branches separately from direct-access
            // branches so the result can propagate the correct FQCN metadata.
            if (isset($inner['enumFqcn'])) {
                $enumResourceFqcns[] = $inner['enumFqcn'];
            }

            if (isset($inner['directEnumFqcn'])) {
                $enumDirectFqcns[] = $inner['directEnumFqcn'];
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

        // Remove a standalone 'null' entry when another type already contains null
        // as a top-level union member (e.g. 'number | null' from a nullable column).
        // This prevents 'number | null | null' when a nullable property is one branch
        // of a ternary and a null literal is the other branch.
        // Heuristic: split each type string by ' | ' and check if 'null' appears as an
        // exact token — this is safe for inline object types because the trailing `}`
        // prevents 'null }' from matching 'null'.
        $explicitNullIndex = array_search('null', $types, true);

        if ($explicitNullIndex !== false && count($types) > 1) {
            $otherTypes = array_values(array_filter($types, fn (string $t): bool => $t !== 'null'));
            $alreadyHasNull = false;

            foreach ($otherTypes as $t) {
                if (in_array('null', explode(' | ', $t), true)) {
                    $alreadyHasNull = true;

                    break;
                }
            }

            if ($alreadyHasNull) {
                unset($types[$explicitNullIndex]);
                $types = array_values($types);
            }
        }

        if ($types === []) {
            return $this->unknownResult(); // @codeCoverageIgnore
        }

        $result = ['type' => implode(' | ', $types), 'optional' => false];

        $enumResourceFqcns = array_values(array_unique($enumResourceFqcns));
        $enumDirectFqcns = array_values(array_unique($enumDirectFqcns));
        $embeddedEnumFqcns = array_values(array_unique($embeddedEnumFqcns));
        $embeddedModelFqcns = array_values(array_unique($embeddedModelFqcns));
        $embeddedResourceFqcns = array_values(array_unique($embeddedResourceFqcns));

        // Determine how to propagate enum FQCN metadata based on which branch sources are present.
        // - Pure EnumResource branches (single FQCN): propagate `enumFqcn` so the transformer
        //   rewrites the type to AsEnum<typeof X> when the tolki package is enabled.
        // - Mixed branches (same FQCN from both EnumResource and direct access): propagate both
        //   `enumFqcn` and `directEnumFqcn` so the transformer produces AsEnum<typeof X> | XType.
        // - Multiple different FQCNs or other complex combinations: fall back to embedded imports.
        if ($enumResourceFqcns !== []) {
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
                $embeddedEnumFqcns = array_values(array_unique([...$allBranchFqcns, ...$embeddedEnumFqcns]));
            }
        } elseif ($enumDirectFqcns !== []) {
            // Only direct-access enum branches: existing embedded behaviour.
            $embeddedEnumFqcns = array_values(array_unique([...$enumDirectFqcns, ...$embeddedEnumFqcns]));
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

        return $result;
    }

    /**
     * Analyze a `$variable->map(fn (TypedClass $item) => [...])` call by resolving the
     * inner closure's typed first parameter class, then analyzing the closure body.
     *
     * When the inner closure has a typed first parameter (e.g. `fn (OrderItem $item) => [...]`),
     * its FQCN (already resolved by NameResolver) is temporarily set as the closure relation
     * model class, and the closure body is analyzed against that class. The result is wrapped
     * as `elementType[]`.
     *
     * Returns null when the inner closure has no typed class parameter, the class is not a
     * Model subclass, or the body analysis resolves to unknown — allowing the caller to fall
     * through to the generic method handler.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeVariableMapCall(MethodCall $call): ?array
    {
        $args = $call->getArgs();

        if ($args === []) {
            return null;
        }

        $closureArg = $args[0]->value;

        if ($closureArg instanceof ArrowFunction) {
            $params = $closureArg->params;
        } elseif ($closureArg instanceof ClosureExpr) {
            $params = $closureArg->params;
        } else {
            return null;
        }

        if ($params === []) {
            return null;
        }

        $firstParam = $params[0];

        // Require a named class type hint (already FQCN-resolved by NameResolver)
        if (! ($firstParam->type instanceof Name)) {
            return null;
        }

        $paramClass = $firstParam->type->toString();

        if (! class_exists($paramClass) || ! is_a($paramClass, Model::class, true)) {
            return null;
        }

        /** @var class-string<Model> $paramClass */
        $previousRelationModel = $this->closureRelationModelClass;
        $this->closureRelationModelClass = $paramClass;

        $returnExprs = $this->resolveClosureReturnExpressions($closureArg);

        $bodyResult = match (count($returnExprs)) {
            0 => null,
            1 => $this->analyzeValueExpression($returnExprs[0]),
            default => $this->analyzeClosureUnion($returnExprs),
        };

        $this->closureRelationModelClass = $previousRelationModel;

        if ($bodyResult === null || $bodyResult['type'] === 'unknown') {
            return null;
        }

        $bodyResult['type'] = $bodyResult['type'].'[]';
        $bodyResult['optional'] = false;

        return $bodyResult;
    }

    /**
     * Analyze a `$variable->pluck('field')` call within a whenLoaded closure context.
     *
     * Resolves the named field's TypeScript type from the related model class and returns
     * it as an array type. Returns `unknown[]` when the field type cannot be determined,
     * which satisfies callers that only need a non-`unknown` result.
     *
     * @return ValueExpressionResult
     */
    private function analyzeVariablePluckCall(MethodCall $call): array
    {
        $args = $call->getArgs();

        if (count($args) >= 1 && $args[0]->value instanceof String_) {
            $fieldName = $args[0]->value->value;
            $info = $this->analyzeRelatedModelProperty($fieldName);

            if ($info['type'] !== 'unknown') {
                $info['type'] = $info['type'].'[]';
                $info['optional'] = false;

                return $info;
            }
        }

        return ['type' => 'unknown[]', 'optional' => false];
    }

    /**
     * When a `when()` condition contains a `$this->propName` expression, bind the first
     * parameter of the closure/arrow-function value to that PropertyFetch expression.
     *
     * This allows expressions like `EnumResource::make($status)` inside a closure passed
     * to `$this->when($this->status !== null, function ($status) { ... })` to be resolved
     * as if they were `EnumResource::make($this->status)`.
     */
    private function bindClosureParamsFromCondition(Expr $condition, Expr $valueExpr): void
    {
        $thisPropExpr = $this->extractThisPropertyFromCondition($condition);

        if ($thisPropExpr === null) {
            return;
        }

        $firstParam = null;

        if ($valueExpr instanceof ArrowFunction && $valueExpr->params !== []) {
            $firstParam = $valueExpr->params[0];
        } elseif ($valueExpr instanceof ClosureExpr && $valueExpr->params !== []) {
            $firstParam = $valueExpr->params[0];
        }

        if ($firstParam === null) {
            return;
        }

        if ($firstParam->var instanceof Variable && is_string($firstParam->var->name)) {
            $this->closureParamExprBindings[$firstParam->var->name] = $thisPropExpr;
        }
    }

    /**
     * Extract a `$this->propName` PropertyFetch expression from a boolean condition.
     *
     * Handles the following patterns:
     * - `$this->prop` — bare property access used as a truthy condition.
     * - `$this->prop !== null` / `null !== $this->prop`
     * - `$this->prop === null` / `null === $this->prop`
     */
    private function extractThisPropertyFromCondition(Expr $condition): ?Expr
    {
        // bare $this->propName
        if ($this->isThisPropertyFetch($condition)) {
            return $condition;
        }

        // $this->propName !== null  or  null !== $this->propName
        if ($condition instanceof BinaryOp\NotIdentical) {
            if ($this->isThisPropertyFetch($condition->left) && $this->isNullConstFetch($condition->right)) {
                return $condition->left;
            }

            if ($this->isThisPropertyFetch($condition->right) && $this->isNullConstFetch($condition->left)) {
                return $condition->right;
            }
        }

        // $this->propName === null  or  null === $this->propName
        if ($condition instanceof BinaryOp\Identical) {
            if ($this->isThisPropertyFetch($condition->left) && $this->isNullConstFetch($condition->right)) {
                return $condition->left;
            }

            if ($this->isThisPropertyFetch($condition->right) && $this->isNullConstFetch($condition->left)) {
                return $condition->right;
            }
        }

        return null;
    }

    /**
     * Return true when the expression is a `null` constant fetch.
     */
    private function isNullConstFetch(Expr $expr): bool
    {
        return $expr instanceof ConstFetch && strtolower($expr->name->toString()) === 'null';
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
