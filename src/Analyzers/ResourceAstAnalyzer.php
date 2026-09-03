<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\FiltersModelAttributes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ResolvesModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\DispatchesFqcnResults;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesSingularResourceClass;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayDimFetch;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
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
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Analyzes a JsonResource's toArray() body to extract property names, types, and optional markers via AST.
 *
 * @phpstan-import-type ResourcePropertyInfoList from MethodAnalysis
 * @phpstan-import-type ClassMapType from MethodAnalysis
 * @phpstan-import-type ImportMapType from MethodAnalysis
 * @phpstan-import-type InlineEnumFqcnsMap from MethodAnalysis
 * @phpstan-import-type InlineModelFqcnsMap from MethodAnalysis
 * @phpstan-import-type MultiEnumFqcnsMap from MethodAnalysis
 * @phpstan-import-type EnumResourceArmShapeMap from MethodAnalysis
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
class ResourceAstAnalyzer implements ExpressionEngine
{
    use ChecksPreserveKeys;
    use CollectsLocalVarBindings;
    use DispatchesFqcnResults;
    use FiltersModelAttributes;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ParsesTsCasts;
    use ResolvesClassNames;
    use ResolvesModelRelationTypes;
    use ResolvesModelTypes;
    use ResolvesRelatedModelTypes;
    use ResolvesSingularResourceClass;

    /** Carries the subject reflection, model class, and all closure/spread bindings; see AnalysisScope. */
    protected AnalysisScope $scope;

    /** Built once per instance by dispatcher(), so the handler-candidate memo survives across dispatches. */
    protected ?ExpressionDispatcher $dispatcher = null;

    /**
     * Create an analyzer for a class, its optional backing model, and the method to analyze.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resourceReflection  templated because ReflectionClass is invariant
     * @param  class-string<Model>|null  $modelClass
     * @param  list<ExpressionHandler>|null  $handlerProfile  overrides the resource profile
     * @param  AnalysisScope|null  $scope  a scope already seeded by AstEngine::bindingsFor(), used as-is
     */
    public function __construct(
        protected ReflectionClass $resourceReflection,
        protected ?string $modelClass = null,
        protected string $methodName = 'toArray',
        protected ?array $handlerProfile = null,
        ?AnalysisScope $scope = null,
    ) {
        $this->scope = $scope ?? new AnalysisScope(
            self::genericReflection($this->resourceReflection->getName()),
            $this->modelClass,
        );

        if ($scope === null) {
            $this->scope->requestVarNames = $this->resolveRequestVarNames($this->methodName);
        }

        if ($this->scope->modelClass !== null) {
            $this->loadModelInspectorData();
        }
    }

    /**
     * Request-typed parameter names of one method, keyed for O(1) lookup.
     *
     * Resources take `toArray(Request $request)` too, but their committed output was inferred without
     * the Request rules; seeding them there would move it, so the resource path opts out.
     *
     * @return array<string, true>
     */
    private function resolveRequestVarNames(string $methodName): array
    {
        if (is_a($this->scope->subjectReflection->getName(), JsonResource::class, true)
            || ! $this->scope->subjectReflection->hasMethod($methodName)) {
            return [];
        }

        $names = [];

        foreach ($this->scope->subjectReflection->getMethod($methodName)->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof ReflectionNamedType && is_a($type->getName(), Request::class, true)) {
                $names[$parameter->getName()] = true;
            }
        }

        return $names;
    }

    /**
     * `ReflectionClass`'s template is invariant, so a caller's `ReflectionClass<JsonResource>` cannot
     * be assigned into `AnalysisScope`'s `<object>` slot; re-reflecting by name erases the generic.
     *
     * @param  class-string  $className
     * @return ReflectionClass<object>
     */
    private static function genericReflection(string $className): ReflectionClass
    {
        return new ReflectionClass($className);
    }

    /**
     * Analyze the subject's $this->methodName body and return the resulting property/type analysis.
     */
    public function analyze(): ResourceAnalysis
    {
        if ($this->scope->modelClass !== null) {
            DependencyRecorder::recordClass($this->scope->modelClass);
        }

        $context = resolve(MethodLocator::class)->locateOwn($this->scope->subjectReflection->getName(), $this->methodName);
        $toArrayMethod = $context?->method;

        if ($toArrayMethod === null || $toArrayMethod->stmts === null) {
            $inherited = $this->analyzeParentToArray();

            // An empty result means no ancestor declared the method either, so keep delegating.
            if ($inherited !== null && $inherited->properties !== []) {
                return $inherited;
            }

            // Model/collection delegation only makes sense for toArray(); a generic method with no
            // body anywhere in the chain is simply empty, not a model dump.
            if ($this->methodName !== 'toArray') {
                return new ResourceAnalysis;
            }

            if ($this->isResourceCollection($this->scope)) {
                return $this->buildCollectionDelegatedAnalysis();
            }

            return $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
        }

        $finder = new NodeFinder;

        $this->scope->instanceOfWrappedClass = $this->resolveInstanceOfType($toArrayMethod, $finder);

        $this->seedVarBindings($toArrayMethod->stmts);

        $branchAnalysis = $this->analyzeAllReturnBranches($toArrayMethod->stmts);

        if ($branchAnalysis !== null) {
            if ($this->scope->subjectReflection->hasMethod($this->methodName)) {
                $this->applyTsCastsFromMethod($this->scope->subjectReflection->getMethod($this->methodName), $branchAnalysis);
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

        if ($this->isParentCallTo($returnStmt->expr, $this->methodName)) {
            return $this->analyzeParentToArray() ?? $this->buildModelDelegatedAnalysis() ?? new ResourceAnalysis;
        }

        // return array_merge(parent::share($request), [...]) — the shape a shared-data middleware writes.
        if ($returnStmt->expr instanceof FuncCall) {
            return $this->analyzeReturnArrayMerge($returnStmt->expr) ?? new ResourceAnalysis;
        }

        // return $this->only([...]) or return $this->except([...])
        if ($returnStmt->expr instanceof MethodCall) {
            $filtered = $this->analyzeThisAttributeFilter($returnStmt->expr);

            if ($filtered !== null) {
                return $filtered;
            }

            // return $this->someMethod() — resolve it the same way an array-literal spread would.
            if ($this->hasThisReceiver($returnStmt->expr) && $returnStmt->expr->name instanceof Identifier) {
                return $this->analyzeThisMethodSpread($returnStmt->expr->name->toString()) ?? new ResourceAnalysis;
            }

            return new ResourceAnalysis;
        }

        return new ResourceAnalysis;
    }

    /**
     * Resolve a single expression to its TypeScript type. ExpressionEngine entry point for handlers.
     *
     * @return ValueExpressionResult
     */
    public function resolve(Expr $expr): array
    {
        return $this->analyzeValueExpression($expr);
    }

    /**
     * Spread-analyze a named method on the subject under analysis. ExpressionEngine entry point
     * for a handler that resolves a self-returning chain onto a non-preserving method body, whose
     * result becomes one value's inline type — never the flatten target, so not top-level.
     */
    public function spreadAnalysis(string $methodName): ?ResourceAnalysis
    {
        return $this->analyzeThisMethodSpread($methodName, topLevel: false);
    }

    /**
     * Array-literal-analyze an expression's items. ExpressionEngine entry point for a caller
     * delegating array analysis — see the interface docblock for what $topLevel means here.
     */
    public function returnArrayAnalysis(Array_ $array, bool $topLevel = false): ResourceAnalysis
    {
        return $this->analyzeReturnArray($array, $topLevel);
    }

    /**
     * Seed the scope's variable bindings from a method body: single-write assignments, then the
     * relation-typed foreach loop variables that only a model-backed scope can resolve.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function seedVarBindings(array $stmts): void
    {
        $this->collectLocalVarBindings($stmts, $this->scope);
        $this->bindForeachLoopVariables($stmts);
    }

    /**
     * Bind a top-level `foreach ($this->{manyRelation} as $item)`'s value variable to the relation's
     * element model, for the whole method — mirrors `collectLocalVarBindings()`'s method-wide scope.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function bindForeachLoopVariables(array $stmts): void
    {
        foreach ($stmts as $stmt) {
            if (! $stmt instanceof Foreach_
                || ! $stmt->valueVar instanceof Variable
                || ! is_string($stmt->valueVar->name)
                || ! $stmt->expr instanceof PropertyFetch
                || ! $this->isThisPropertyFetch($stmt->expr)
                || ! $stmt->expr->name instanceof Identifier
            ) {
                continue;
            }

            $relationInfo = $this->resolveModelRelationTypeInfo($stmt->expr->name->toString(), $this->scope);

            if (str_ends_with($relationInfo['type'], '[]') && $relationInfo['modelFqcn'] !== null) {
                $this->scope->varModelBindings[$stmt->valueVar->name] = $relationInfo['modelFqcn'];
            }
        }
    }

    /**
     * Analyze a returned array literal into properties, spreads, and FQCN tracking maps.
     *
     * $topLevel gates the top-level-only resolve()/toArray() spread flatten (A24): true when this
     * literal IS a whole return shape (a resource's own, or an opted-in caller like Inertia's props);
     * false — one value's own inline type — threads down through every method this recurses into.
     */
    protected function analyzeReturnArray(Array_ $array, bool $topLevel = true): ResourceAnalysis
    {
        $analysis = new ResourceAnalysis;

        foreach ($array->items as $item) {
            // Handle ...parent::toArray($request) spread
            if ($item->key === null && $item->unpack && $this->isParentCallTo($item->value, $this->methodName)) {
                $parentAnalysis = $this->analyzeParentToArray();

                if ($parentAnalysis !== null) {
                    $analysis->merge($parentAnalysis);
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
                    $analysis->merge($filterAnalysis);
                }

                continue;
            }

            // Handle ...$this->method() spread (e.g., trait methods returning arrays)
            if ($item->key === null && $item->unpack
                && $item->value instanceof MethodCall
                && $item->value->var instanceof Variable
                && $item->value->var->name === 'this'
                && $item->value->name instanceof Identifier) {
                $spreadAnalysis = $this->analyzeThisMethodSpread($item->value->name->toString(), $topLevel);

                if ($spreadAnalysis !== null) {
                    $analysis->merge($spreadAnalysis);
                }

                continue;
            }

            // Handle ...functionCall() spread (bare trait method calls without $this->)
            if ($item->key === null && $item->unpack && $item->value instanceof FuncCall) {
                /** @var Node $funcCallName */
                $funcCallName = $item->value->name;

                if ($funcCallName instanceof Name) {
                    $funcName = $funcCallName->getLast();

                    if ($this->scope->subjectReflection->hasMethod($funcName)) {
                        $spreadAnalysis = $this->analyzeThisMethodSpread($funcName, $topLevel);

                        if ($spreadAnalysis !== null) {
                            $analysis->merge($spreadAnalysis);
                        }
                    }
                }

                continue;
            }

            // Handle a top-level resolve()/toArray() spread — flatten it into this resource's own properties.
            if ($topLevel && $item->key === null && $item->unpack) {
                $armAnalysis = $this->analyzeSpreadArm($item->value);

                if ($armAnalysis !== null) {
                    $analysis->merge($armAnalysis);

                    continue;
                }
            }

            // Handle $this->merge([...]) or $this->mergeWhen(condition, [...])
            if ($item->key === null && $item->value instanceof MethodCall) {
                $mergeResult = $this->analyzeMergeExpression($item->value);

                $analysis->merge($mergeResult);

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
            unset(
                $analysis->enumResources[$keyName], $analysis->nestedResources[$keyName], $analysis->directEnumFqcns[$keyName],
                $analysis->modelFqcns[$keyName], $analysis->multiEnumResourceFqcns[$keyName], $analysis->inlineEnumFqcns[$keyName],
                $analysis->inlineModelFqcns[$keyName], $analysis->inlineEnumResourceFqcns[$keyName],
                $analysis->enumResourceArmShapes[$keyName],
            );

            $analysis->properties[] = [
                'name' => $keyName,
                'type' => $result['type'],
                'optional' => $result['optional'],
                'description' => '',
            ];

            $this->dispatchFqcnResults(
                $keyName, $result, $analysis->enumResources, $analysis->directEnumFqcns,
                $analysis->nestedResources, $analysis->modelFqcns, $analysis->multiEnumResourceFqcns,
                $analysis->enumResourceArmShapes,
            );

            foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
                $analysis->inlineEnumFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
                $analysis->inlineEnumResourceFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
                $analysis->inlineModelFqcns[$keyName][] = $fqcn;
            }

            foreach ($result['customImports'] ?? [] as $path => $types) {
                $analysis->customImports[$path] = [...($analysis->customImports[$path] ?? []), ...$types];
            }
        }

        return $analysis;
    }

    /**
     * Classify a top-level spread's value expression via InlineArrayHandler::classifySpreadArm()
     * and flatten its shape into this resource's own properties. Null for anything the classifier
     * declines, so the caller falls through to $this->merge()'s own handling.
     */
    private function analyzeSpreadArm(Expr $expr): ?MethodAnalysis
    {
        $arm = InlineArrayHandler::classifySpreadArm($expr, $this->scope, $this);

        if ($arm === null) {
            return null;
        }

        if (! $arm['isModel']) {
            /** @var class-string<JsonResource> $resourceFqcn */
            $resourceFqcn = $arm['fqcn'];

            return $this->analyzeResourceSpreadArm($resourceFqcn);
        }

        /** @var class-string<Model> $modelFqcn */
        $modelFqcn = $arm['fqcn'];

        return $arm['isCollection']
            ? $this->analyzeCollectionSpreadArm($modelFqcn)
            : $this->analyzeModelSpreadArm($modelFqcn);
    }

    /**
     * Flatten a spread resource's own toArray() into this resource, the way analyzeThisMethodSpread()
     * merges a spread method's analysis — nested resources, model FQCNs, and enum channels travel
     * with it via MethodAnalysis::merge().
     *
     * @param  class-string<JsonResource>  $resourceFqcn
     */
    private function analyzeResourceSpreadArm(string $resourceFqcn): MethodAnalysis
    {
        DependencyRecorder::recordClass($resourceFqcn);

        return resolve(AstEngine::class)->analyzeMethod($resourceFqcn, 'toArray');
    }

    /**
     * Flatten a spread model's toArray() into one property per published column, typed the same
     * way ThisPropertyHandler types a `$this->column` access, honoring that model's own #[TsCasts]
     * overrides — the same refinement the canonical model interface a bare-model arm references gets.
     *
     * @param  class-string<Model>  $modelFqcn
     */
    private function analyzeModelSpreadArm(string $modelFqcn): ResourceAnalysis
    {
        DependencyRecorder::recordClass($modelFqcn);

        $resolver = resolve(ModelAttributeResolver::class);
        $tsCasts = $this->parseTsCastsFromReflection(new ReflectionClass($modelFqcn));
        $properties = [];
        $directEnumFqcns = [];
        $customImports = [];

        // toArray() is columns plus $appends (accessor attributes explicitly opted into
        // serialization) — a mutator with no $appends entry never reaches it, so stays out.
        $appends = $resolver->getInstance($modelFqcn)?->getAppends() ?? [];
        $names = [...$resolver->publishedColumnNames($modelFqcn), ...$appends];

        foreach ($names as $column) {
            $override = $tsCasts['overrides'][$column] ?? null;

            if ($override !== null) {
                $type = $override;
                $enumFqcn = null;

                $importPath = $tsCasts['importPaths'][$column] ?? null;

                if ($importPath !== null) {
                    foreach (LaravelTsPublish::extractImportableTypes($type) as $importName) {
                        $customImports[$importPath][] = $importName;
                    }
                }
            } else {
                $tsInfo = $resolver->resolveAttribute($modelFqcn, $column);

                // Mirrors ModelTransformer::resolveMutatorType()'s own omit check: no getter, no
                // docblock generic, no backing column — nothing to publish for this name.
                if ($tsInfo['omit'] ?? false) {
                    continue;
                }

                $type = $tsInfo['type'];
                $enumFqcn = $tsInfo['enumFqcns'][0] ?? null;
            }

            $properties[] = [
                'name' => $column,
                'type' => $type,
                'optional' => $tsCasts['optionalOverrides'][$column] ?? false,
                'description' => '',
            ];

            if ($enumFqcn !== null) {
                $directEnumFqcns[$column] = $enumFqcn;
            }
        }

        return new ResourceAnalysis(properties: $properties, directEnumFqcns: $directEnumFqcns, customImports: $customImports);
    }

    /**
     * Flatten a spread collection's toArray() into an index-signature member: spreading a collection
     * renumbers its elements 0..n at runtime, so no single named property can stand in for it. Same
     * `Record<number, Model>` shape InlineArrayHandler emits for a nested spread, spelled as a member.
     *
     * @param  class-string<Model>  $modelFqcn
     */
    private function analyzeCollectionSpreadArm(string $modelFqcn): ResourceAnalysis
    {
        DependencyRecorder::recordClass($modelFqcn);

        $indexKey = '[key: number]';

        return new ResourceAnalysis(
            properties: [[
                'name' => $indexKey,
                'type' => LaravelTsPublish::resourceTypeName($modelFqcn),
                'optional' => false,
                'description' => '',
            ]],
            modelFqcns: [$indexKey => $modelFqcn],
        );
    }

    /**
     * Analyze a returned `array_merge(...)` through the array-literal it is equivalent to.
     *
     * Declines the whole call when an argument is neither a literal nor `parent::{$this->methodName}()`.
     */
    protected function analyzeReturnArrayMerge(FuncCall $call): ?ResourceAnalysis
    {
        $merged = $this->mergedArrayLiteral($call, $this->methodName);

        return $merged === null ? null : $this->analyzeReturnArray($merged);
    }

    /**
     * The ordered handler chain: the injected profile when one was supplied, else the resource
     * profile — see ResourceExpressionHandlers for the list and its ordering contract.
     *
     * @return list<ExpressionHandler>
     */
    protected function handlers(): array
    {
        return $this->handlerProfile ?? ResourceExpressionHandlers::make($this);
    }

    /**
     * Lazily build the dispatcher once per instance — a per-call rebuild would defeat its memo.
     */
    protected function dispatcher(): ExpressionDispatcher
    {
        return $this->dispatcher ??= new ExpressionDispatcher($this->handlers());
    }

    /**
     * Analyze a value expression and return its type + optional status.
     *
     * @return ValueExpressionResult
     */
    protected function analyzeValueExpression(Expr $expr): array
    {
        return $this->dispatcher()->dispatch($expr, $this->scope, $this) ?? ValueResult::unknown();
    }

    /**
     * Analyze $this->merge([...]), mergeWhen(condition, [...]), or mergeUnless(condition, [...]).
     *
     * merge() properties are required; mergeWhen()/mergeUnless() properties are optional.
     */
    protected function analyzeMergeExpression(MethodCall $call): ResourceAnalysis
    {
        $isMerge = $this->isThisMethodCall($call, 'merge');
        $isMergeWhen = $this->isThisMethodCall($call, 'mergeWhen');
        $isMergeUnless = $this->isThisMethodCall($call, 'mergeUnless');

        if (! $isMerge && ! $isMergeWhen && ! $isMergeUnless) {
            return new ResourceAnalysis;
        }

        if ($call->isFirstClassCallable()) {
            return new ResourceAnalysis; // @codeCoverageIgnore
        }

        $method = $isMerge ? 'merge' : ($isMergeWhen ? 'mergeWhen' : 'mergeUnless');
        $value = CallArguments::for($call, new ReflectionMethod(JsonResource::class, $method))->named('value');

        if ($value === null) {
            return new ResourceAnalysis;
        }

        return $this->resolveArrayOrClosureToProperties($value->value, optional: ! $isMerge);
    }

    /**
     * Resolve an expression that's either an Array_ literal or a closure returning an Array_ into properties.
     * Handles multi-return closures (e.g. guard clause + data branch) by merging all branches.
     */
    protected function resolveArrayOrClosureToProperties(Expr $expr, bool $optional): ResourceAnalysis
    {
        if ($expr instanceof Array_) {
            return (new ThisPropertyHandler)->extractPropertiesFromArray($expr, $this, $optional);
        }

        $returnExprs = $this->resolveClosureReturnExpressions($expr);

        // Filter to non-empty Array_ expressions (skip guard clause `return []`)
        /** @var list<Array_> $arrays */
        $arrays = array_values(array_filter($returnExprs, fn (Expr $e) => $e instanceof Array_ && count($e->items) > 0));

        if ($arrays === []) {
            return new ResourceAnalysis;
        }

        if (count($arrays) === 1) {
            return (new ThisPropertyHandler)->extractPropertiesFromArray($arrays[0], $this, $optional);
        }

        $analyses = array_map(fn (Array_ $a) => (new ThisPropertyHandler)->extractPropertiesFromArray($a, $this, $optional), $arrays);

        return $this->mergeReturnBranches($analyses);
    }

    /**
     * Resolve and analyze the parent class's declaration of $this->methodName.
     */
    protected function analyzeParentToArray(): ?ResourceAnalysis
    {
        $parentClass = $this->scope->subjectReflection->getParentClass();

        if ($parentClass === false) {
            return null;
        }

        if ($parentClass->getName() === JsonResource::class) {
            return $this->methodName === 'toArray' ? $this->buildModelDelegatedAnalysis() : null;
        }

        $parentAnalyzer = new self(
            $parentClass,
            $this->scope->modelClass,
            $this->methodName,
            $this->handlerProfile,
        );

        return $parentAnalyzer->analyze();
    }

    /**
     * Resolve and analyze a $this->method() spread; $topLevel carries the caller's own
     * flatten-eligibility down into the target's own return (see analyzeReturnArray()).
     * $localVarBindings/$resolvingLocalVars/$varModelBindings save/clear/restore via `finally`.
     */
    protected function analyzeThisMethodSpread(string $methodName, bool $topLevel = true): ?ResourceAnalysis
    {
        if (! $this->scope->subjectReflection->hasMethod($methodName)) {
            return null; // @codeCoverageIgnore
        }

        if (isset($this->scope->visitedSpreadMethods[$methodName])) {
            return null;
        }

        $method = $this->scope->subjectReflection->getMethod($methodName);
        $context = resolve(MethodLocator::class)->locate($this->scope->subjectReflection->getName(), $methodName);
        $targetMethod = $context?->method;

        if ($targetMethod === null || $targetMethod->stmts === null) {
            return null;
        }

        $this->scope->visitedSpreadMethods[$methodName] = true;

        $finder = new NodeFinder;

        $previousLocalVarBindings = $this->scope->localVarBindings;
        $previousResolvingLocalVars = $this->scope->resolvingLocalVars;
        $previousVarModelBindings = $this->scope->varModelBindings;
        $previousRequestVarNames = $this->scope->requestVarNames;
        $this->scope->localVarBindings = [];
        $this->scope->resolvingLocalVars = [];
        $this->scope->varModelBindings = [];
        // The spread method has its own signature: the entry method's Request params say nothing
        // about which of ITS variables hold one. analyzeParentToArray() re-derives the same way.
        $this->scope->requestVarNames = $this->resolveRequestVarNames($methodName);
        $this->seedVarBindings($targetMethod->stmts);

        try {
            $returnStmt = $finder->findFirst($targetMethod->stmts, function (Node $node): bool {
                return $node instanceof Return_;
            });

            if ($returnStmt instanceof Return_ && $returnStmt->expr instanceof Array_) {
                $analysis = $this->analyzeReturnArray($returnStmt->expr, $topLevel);
            } elseif ($returnStmt instanceof Return_ && $returnStmt->expr instanceof Variable
                && is_string($returnStmt->expr->name)) {
                $analysis = $this->resolveVariableReturnAnalysis($targetMethod->stmts, $returnStmt->expr->name, $topLevel);
            } elseif ($returnStmt instanceof Return_ && $returnStmt->expr instanceof MethodCall) {
                $filtered = $this->analyzeThisAttributeFilter($returnStmt->expr);

                if ($filtered !== null) {
                    $analysis = $filtered;
                } elseif ($this->hasThisReceiver($returnStmt->expr) && $returnStmt->expr->name instanceof Identifier) {
                    $analysis = $this->analyzeThisMethodSpread($returnStmt->expr->name->toString(), $topLevel) ?? new ResourceAnalysis;
                } else {
                    $analysis = new ResourceAnalysis;
                }
            } else {
                $analysis = new ResourceAnalysis;
            }
        } finally {
            $this->scope->localVarBindings = $previousLocalVarBindings;
            $this->scope->resolvingLocalVars = $previousResolvingLocalVars;
            $this->scope->varModelBindings = $previousVarModelBindings;
            $this->scope->requestVarNames = $previousRequestVarNames;
            unset($this->scope->visitedSpreadMethods[$methodName]);
        }

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

        $this->applyTsCastsFromMethod($method, $analysis);

        return $analysis;
    }

    /**
     * Apply #[TsCasts] overrides declared on a reflection method, updating or injecting properties.
     *
     * Accepted on trait/helper methods and on toArray() itself, as a lightweight override mechanism.
     */
    private function applyTsCastsFromMethod(ReflectionMethod $method, ResourceAnalysis $analysis): void
    {
        foreach ($method->getAttributes(TsCasts::class) as $attr) {
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
    protected function resolveVariableReturnAnalysis(array $stmts, string $varName, bool $topLevel = true): ResourceAnalysis
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
        /** @var EnumResourceArmShapeMap $enumResourceArmShapes */
        $enumResourceArmShapes = [];

        $this->collectVariableArrayAssignments(
            $stmts, $varName, false,
            $properties, $enumResources, $nestedResources,
            $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
            $inlineEnumResourceFqcns, $enumResourceArmShapes, $topLevel,
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
            enumResourceArmShapes: $enumResourceArmShapes,
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
     * @param  EnumResourceArmShapeMap  $enumResourceArmShapes
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
        array &$enumResourceArmShapes = [],
        bool $topLevel = true,
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
                $baseAnalysis = $this->analyzeReturnArray($stmt->expr->expr, $topLevel);

                if ($isConditional) {
                    foreach ($baseAnalysis->properties as &$prop) {
                        $prop['optional'] = true;
                    }

                    unset($prop);
                }

                $accumulator = new ResourceAnalysis(
                    $properties, $enumResources, $nestedResources,
                    customImports: $customImports,
                    directEnumFqcns: $directEnumFqcns,
                    modelFqcns: $modelFqcns,
                    inlineEnumFqcns: $inlineEnumFqcns,
                    inlineModelFqcns: $inlineModelFqcns,
                    multiEnumResourceFqcns: $multiEnumResourceFqcns,
                    inlineEnumResourceFqcns: $inlineEnumResourceFqcns,
                    enumResourceArmShapes: $enumResourceArmShapes,
                );
                $accumulator->merge($baseAnalysis);

                $properties = $accumulator->properties;
                $enumResources = $accumulator->enumResources;
                $nestedResources = $accumulator->nestedResources;
                $directEnumFqcns = $accumulator->directEnumFqcns;
                $modelFqcns = $accumulator->modelFqcns;
                $customImports = $accumulator->customImports;
                $inlineEnumFqcns = $accumulator->inlineEnumFqcns;
                $inlineModelFqcns = $accumulator->inlineModelFqcns;
                $multiEnumResourceFqcns = $accumulator->multiEnumResourceFqcns;
                $inlineEnumResourceFqcns = $accumulator->inlineEnumResourceFqcns;
                $enumResourceArmShapes = $accumulator->enumResourceArmShapes;

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
                    $enumResourceArmShapes[$keyName],
                );

                $this->dispatchFqcnResults(
                    $keyName, $result, $enumResources, $directEnumFqcns, $nestedResources, $modelFqcns,
                    $multiEnumResourceFqcns, $enumResourceArmShapes,
                );

                foreach ($result['embeddedEnumFqcns'] ?? [] as $fqcn) {
                    $inlineEnumFqcns[$keyName][] = $fqcn;
                }

                foreach ($result['embeddedEnumResourceFqcns'] ?? [] as $fqcn) {
                    $inlineEnumResourceFqcns[$keyName][] = $fqcn;
                }

                foreach ($result['embeddedModelFqcns'] ?? [] as $fqcn) {
                    $inlineModelFqcns[$keyName][] = $fqcn;
                }

                foreach ($result['customImports'] ?? [] as $path => $types) {
                    $customImports[$path] = [...($customImports[$path] ?? []), ...$types];
                }

                continue;
            }

            if ($stmt instanceof If_) {
                $this->collectVariableArrayAssignments(
                    $stmt->stmts, $varName, true,
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                    $inlineEnumResourceFqcns, $enumResourceArmShapes, $topLevel,
                );

                foreach ($stmt->elseifs as $elseif) {
                    $this->collectVariableArrayAssignments(
                        $elseif->stmts, $varName, true,
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns, $enumResourceArmShapes, $topLevel,
                    );
                }

                if ($stmt->else !== null) {
                    $this->collectVariableArrayAssignments(
                        $stmt->else->stmts, $varName, true,
                        $properties, $enumResources, $nestedResources,
                        $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                        $inlineEnumResourceFqcns, $enumResourceArmShapes, $topLevel,
                    );
                }
            }

            // Loop bodies are conditional: a loop may execute zero times.
            if ($stmt instanceof Foreach_ || $stmt instanceof For_
                || $stmt instanceof While_ || $stmt instanceof Do_) {
                $this->collectVariableArrayAssignments(
                    $stmt->stmts, $varName, true,
                    $properties, $enumResources, $nestedResources,
                    $directEnumFqcns, $modelFqcns, $customImports, $inlineEnumFqcns, $inlineModelFqcns, $multiEnumResourceFqcns,
                    $inlineEnumResourceFqcns, $enumResourceArmShapes, $topLevel,
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

    /**
     * Build a ResourceAnalysis for a ResourceCollection subclass that has no toArray() method.
     *
     * A non-empty $wrap key produces `{ data: R[] }`, keyed as `Record<string, R>` when the collection
     * preserves keys; a null $wrap makes that same element type the flatTypeAlias directly.
     */
    protected function buildCollectionDelegatedAnalysis(): ResourceAnalysis
    {
        $singular = $this->resolveSingularResourceClass($this->scope);

        if ($singular === null) {
            return new ResourceAnalysis;
        }

        // JsonResource declares `public static $wrap = 'data'`, so reflection always finds a value; an
        // inherited `$wrap = null` is as deliberate as an own one.
        /** @var string|null $wrapKey */
        $wrapKey = $this->scope->subjectReflection->hasProperty('wrap')
            ? $this->scope->subjectReflection->getProperty('wrap')->getDefaultValue()
            : 'data';

        $elementType = $this->wrapCollectionElementType(LaravelTsPublish::resourceTypeName($singular), $this->scope->subjectReflection);

        if ($wrapKey === null || $wrapKey === '') {
            return new ResourceAnalysis(flatTypeAlias: $elementType, flatTypeAliasFqcn: $singular);
        }

        $key = $wrapKey ? $wrapKey : 'data';

        return new ResourceAnalysis(
            properties: [[
                'name' => $key,
                'type' => $elementType,
                'optional' => false,
                'description' => '',
            ]],
            nestedResources: [$wrapKey => $singular],
        );
    }

    /**
     * Analyze all direct Return_ statements yielding non-empty Array_ literals, merging multiple
     * branches with union semantics: properties present in only some branches become optional.
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

        if (count($candidates) === 1) {
            /** @var Array_ $expr */
            $expr = $candidates[0]->expr;

            return $this->analyzeReturnArray($expr);
        }

        $analyses = array_map(function (Return_ $r) {
            /** @var Array_ $expr */
            $expr = $r->expr;

            return $this->analyzeReturnArray($expr);
        }, $candidates);

        return $this->mergeReturnBranches($analyses);
    }

    /**
     * Merge ResourceAnalysis objects from different return branches: a property missing from any
     * branch becomes optional, every map channel unions per key — inlineModelFqcns per occurrence,
     * the enum maps deduped — and flatTypeAlias/flatTypeAliasFqcn keep the first non-null branch value.
     *
     * Public because the page analyzer merges one component's several `Inertia::render()` calls by
     * exactly these rules.
     *
     * @param  list<ResourceAnalysis>  $analyses
     */
    public function mergeReturnBranches(array $analyses): ResourceAnalysis
    {
        $branchCount = count($analyses);

        /** @var array<string, list<array{type: string, optional: bool, description: string}>> */
        $propertyMap = [];

        $enumResources = [];
        $nestedResources = [];
        $directEnumFqcns = [];
        $modelFqcns = [];
        $customImports = [];
        /** @var MultiEnumFqcnsMap $multiEnumResourceFqcns */
        $multiEnumResourceFqcns = [];
        /** @var InlineEnumFqcnsMap $inlineEnumFqcns */
        $inlineEnumFqcns = [];
        /** @var InlineModelFqcnsMap $inlineModelFqcns */
        $inlineModelFqcns = [];
        /** @var InlineEnumFqcnsMap $inlineEnumResourceFqcns */
        $inlineEnumResourceFqcns = [];
        /** @var EnumResourceArmShapeMap $enumResourceArmShapes */
        $enumResourceArmShapes = [];
        $flatTypeAlias = null;
        $flatTypeAliasFqcn = null;

        foreach ($analyses as $analysis) {
            foreach ($analysis->properties as $prop) {
                $propertyMap[$prop['name']][] = $prop;
            }

            $enumResources = [...$enumResources, ...$analysis->enumResources];
            $nestedResources = [...$nestedResources, ...$analysis->nestedResources];
            $directEnumFqcns = [...$directEnumFqcns, ...$analysis->directEnumFqcns];
            $modelFqcns = [...$modelFqcns, ...$analysis->modelFqcns];
            $multiEnumResourceFqcns = [...$multiEnumResourceFqcns, ...$analysis->multiEnumResourceFqcns];
            $enumResourceArmShapes = [...$enumResourceArmShapes, ...$analysis->enumResourceArmShapes];
            $flatTypeAlias ??= $analysis->flatTypeAlias;
            $flatTypeAliasFqcn ??= $analysis->flatTypeAliasFqcn;

            foreach ($analysis->customImports as $path => $names) { // @codeCoverageIgnoreStart
                $customImports[$path] = array_values(array_unique([
                    ...($customImports[$path] ?? []),
                    ...$names,
                ]));
            } // @codeCoverageIgnoreEnd

            // inlineEnumFqcns/inlineEnumResourceFqcns append WITHOUT deduping, same as
            // MethodAnalysis::merge() — aliasPropertyType() consumes each positionally.
            foreach ($analysis->inlineEnumFqcns as $propName => $fqcns) {
                $inlineEnumFqcns[$propName] = [...($inlineEnumFqcns[$propName] ?? []), ...$fqcns];
            }

            foreach ($analysis->inlineModelFqcns as $propName => $fqcns) {
                $inlineModelFqcns[$propName] = [...($inlineModelFqcns[$propName] ?? []), ...$fqcns];
            }

            foreach ($analysis->inlineEnumResourceFqcns as $propName => $fqcns) {
                $inlineEnumResourceFqcns[$propName] = [...($inlineEnumResourceFqcns[$propName] ?? []), ...$fqcns];
            }
        }

        /** @var list<array{name: string, type: string, optional: bool, description: string}> */
        $properties = [];

        foreach ($propertyMap as $name => $entries) {
            $types = array_values(array_unique(array_column($entries, 'type')));
            $type = count($types) === 1 ? $types[0] : $this->unionBranchTypes($types);

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
            inlineEnumFqcns: $inlineEnumFqcns,
            inlineModelFqcns: $inlineModelFqcns,
            multiEnumResourceFqcns: $multiEnumResourceFqcns,
            inlineEnumResourceFqcns: $inlineEnumResourceFqcns,
            enumResourceArmShapes: $enumResourceArmShapes,
            flatTypeAlias: $flatTypeAlias,
            flatTypeAliasFqcn: $flatTypeAliasFqcn,
        );
    }

    /**
     * Union branch type strings, hoisting one trailing `| null` rather than repeating the marker each
     * nullsafe branch already carries. Only top-level nulls move; a nested one belongs to its member.
     *
     * @param  list<string>  $types
     */
    private function unionBranchTypes(array $types): string
    {
        return LaravelTsPublish::hoistNull($types);
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
     * Extract an instanceof type hint from a guard clause in toArray(), positive or negated —
     * e.g. `if (! $this->resource instanceof ClassName) { return []; }`.
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
}
