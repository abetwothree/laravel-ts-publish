<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\FiltersModelAttributes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ResolvesModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsInstanceofGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsReturnedVariables;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesSingularResourceClass;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Concerns\ParsesTsCasts;
use AbeTwoThree\LaravelTsPublish\Concerns\ResolvesClassNames;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\ModelAttributeResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Do_;
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
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type RequestVarNamesMap from AnalysisScope
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type AnalyzedPropertyList from MethodAnalysis
 * @phpstan-import-type CastMap from MethodAnalysis
 *
 * @internal
 */
class ResourceAstAnalyzer implements ExpressionEngine
{
    use ChecksPreserveKeys;
    use CollectsInstanceofGuards;
    use CollectsLocalVarBindings;
    use FiltersModelAttributes;
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use InspectsResourceSubject;
    use ParsesTsCasts;
    use ReadsNonNullGuards;

    /** @use ReadsReturnedVariables<ResourceAnalysis> */
    use ReadsReturnedVariables;

    use ResolvesClassNames;
    use ResolvesModelRelationTypes;
    use ResolvesModelTypes;
    use ResolvesRelatedModelTypes;
    use ResolvesSingularResourceClass;

    /** Carries the subject reflection, model class, and all closure/spread bindings; see AnalysisScope. */
    protected AnalysisScope $scope;

    /** Built once per instance by dispatcher(), so the handler-candidate memo survives across dispatches. */
    protected ?ExpressionDispatcher $dispatcher = null;

    /** The class declaring the share() whose casts InertiaSharedDataAnalyzer applies; a parent's analyzer keeps it. */
    protected ?string $sharedDataShareClass = null;

    /**
     * Create an analyzer for a class, its optional backing model, and the method to analyze.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resourceReflection  templated because ReflectionClass is invariant
     * @param  class-string<Model>|null  $modelClass
     * @param  list<ExpressionHandler>|null  $handlerProfile  overrides the subject's own profile
     * @param  AnalysisScope|null  $scope  a scope already seeded by AstEngine::bindingsFor(), used as-is
     * @param  MethodContext|null  $context  a context already located for $methodName, used instead of locating one
     * @param  bool  $carriesImports  seeds AnalysisScope::$carriesImports; a supplied scope keeps its own
     */
    public function __construct(
        protected ReflectionClass $resourceReflection,
        protected ?string $modelClass = null,
        protected string $methodName = 'toArray',
        protected ?array $handlerProfile = null,
        ?AnalysisScope $scope = null,
        protected ?MethodContext $context = null,
        bool $carriesImports = true,
    ) {
        $this->scope = $scope ?? new AnalysisScope(
            self::genericReflection($this->resourceReflection->getName()),
            $this->modelClass,
        );

        if ($scope === null) {
            $this->scope->requestVarNames = $this->resolveRequestVarNames($this->methodName);
            $this->scope->carriesImports = $carriesImports;
        }

        if ($this->scope->modelClass !== null) {
            $this->loadModelInspectorData();
        }
    }

    /**
     * Request-typed parameter names of one method, keyed to their bound class for O(1) lookup.
     *
     * Resources take `toArray(Request $request)` too, but their committed output was inferred without
     * the Request rules; seeding them there would move it, so the resource path opts out.
     *
     * @return RequestVarNamesMap
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
                /** @var class-string<Request> $class */
                $class = $type->getName();
                $names[$parameter->getName()] = $class;
            }
        }

        return $names;
    }

    /**
     * The class an undeclared `$this->member` forwards to: a JsonResource proxies both reads and calls to
     * its backing. AnalysisScope derives this from the subject; this re-derives it once an `instanceof`
     * guard has supplied a backing the constructor had none for.
     *
     * @return class-string|null
     */
    private function proxyTarget(): ?string
    {
        $backing = $this->scope->modelClass ?? $this->scope->instanceOfWrappedClass;

        return $backing !== null && $this->scope->subjectReflection->isSubclassOf(JsonResource::class)
            ? $backing
            : null;
    }

    /**
     * Locate the subject's own declaration of the analyzed method, a trait's included.
     *
     * A body in a trait's file reads its `@var` tags against that file's imports, as a spread helper's does.
     */
    private function locateSubjectMethod(): ?MethodContext
    {
        $subject = $this->scope->subjectReflection;
        $context = resolve(MethodLocator::class)->locateDeclared($subject->getName(), $this->methodName);

        if ($context === null) {
            return null;
        }

        $method = $subject->getMethod($this->methodName);

        if ($method->getFileName() !== $subject->getFileName()) {
            $this->scope->declaringFileClass = LaravelTsPublish::methodDeclaringFileClass($method);
        }

        return $context;
    }

    /**
     * Whether the subject is a ResourceCollection whose toArray() is Laravel's own, declared by no class in between.
     */
    private function runsFrameworkCollectionToArray(): bool
    {
        return $this->methodName === 'toArray'
            && $this->isResourceCollection($this->scope)
            && $this->scope->subjectReflection->getMethod('toArray')->getDeclaringClass()->getName() === ResourceCollection::class;
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

        $context = $this->context ?? $this->locateSubjectMethod();
        $toArrayMethod = $context?->method;

        if ($toArrayMethod === null || $toArrayMethod->stmts === null) {
            $ownCollection = $this->runsFrameworkCollectionToArray() ? $this->buildCollectionDelegatedAnalysis() : null;

            // Laravel's collects() reads #[Collects], $collects and the naming convention off static::class, so the
            // class collects what it names itself; a parent's answer stands only where the class names nothing.
            if ($ownCollection !== null && ($ownCollection->properties !== [] || $ownCollection->flatTypeAlias !== null)) {
                return $ownCollection;
            }

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
                return $ownCollection ?? $this->buildCollectionDelegatedAnalysis();
            }

            $delegated = $this->buildModelSerializedAnalysis();

            // With no model behind it, the delegation publishes none of the keys the response carries.
            if ($delegated === null) {
                $this->lenientReads++;
            }

            return $delegated ?? new ResourceAnalysis;
        }

        $finder = new NodeFinder;

        $this->scope->instanceOfWrappedClass = $this->resolveInstanceOfType($toArrayMethod, $finder);
        $this->scope->forwardsUndeclaredMembersTo = $this->proxyTarget();

        $this->seedVarBindings($toArrayMethod->stmts);

        $branchAnalysis = $this->analyzeAllReturnBranches($toArrayMethod->stmts);

        if ($branchAnalysis !== null) {
            if ($this->scope->subjectReflection->hasMethod($this->methodName)) {
                $ownMethod = $this->scope->subjectReflection->getMethod($this->methodName);

                resolve(ReturnShapeRefiner::class)->refine($branchAnalysis, $ownMethod, keepsUnresolvedNames: false);

                // InertiaSharedDataAnalyzer lays this share()'s casts over the props itself, with the docblock and `?`.
                if (! $this->isInertiaShare($ownMethod)) {
                    $this->applyTsCastsFromMethod($ownMethod, $branchAnalysis);
                }

                resolve(IndexSignatureReconciler::class)->reconcile($branchAnalysis);
            }

            return $branchAnalysis;
        }

        // Fallback: the method's own first return for non-array returns (parent::toArray, $this->only, etc.); a
        // closure's return is the closure's value, never the method's.
        $returned = $this->collectReturnExpressions($toArrayMethod->stmts)[0] ?? null;

        if ($returned === null) {
            return new ResourceAnalysis; // @codeCoverageIgnore
        }

        if ($returned instanceof Variable && is_string($returned->name)) {
            return $this->resolveVariableReturnAnalysis($toArrayMethod->stmts, $returned->name);
        }

        $analysis = $this->analyzeArrayExpression($returned);

        if ($analysis === null) {
            $this->lenientReads++;
        }

        return $analysis ?? new ResourceAnalysis;
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
     * The analysis of a whole array a method returns or builds a variable from: a literal, a `parent::` call to the
     * method itself, an `array_merge()` of those, an own-model `only()`/`except()` or a `$this->method()` call; null
     * for any other expression.
     */
    protected function analyzeArrayExpression(Expr $expr, bool $topLevel = true): ?ResourceAnalysis
    {
        if ($expr instanceof Array_) {
            return $this->analyzeReturnArray($expr, $topLevel);
        }

        if ($this->isParentCallTo($expr, $this->methodName)) {
            return $this->analyzeParentToArray();
        }

        // array_merge(parent::share($request), [...]) — the shape a shared-data middleware writes.
        if ($expr instanceof FuncCall) {
            return $this->analyzeReturnArrayMerge($expr, $topLevel);
        }

        if (! $expr instanceof MethodCall || ! $expr->name instanceof Identifier) {
            return null;
        }

        $filtered = $this->analyzeThisAttributeFilter($expr);

        // A bare $this->someMethod() resolves the same way an array-literal spread of it would.
        return $filtered ?? ($this->hasThisReceiver($expr)
            ? $this->analyzeThisMethodSpread($expr->name->toString(), $topLevel, $expr)
            : null);
    }

    /**
     * Whether analyzeArrayExpression() reads an expression, decided without analyzing it: a gate that analyzed a write
     * the walk then analyzes again would count its dropped union arms twice.
     */
    protected function readsAsArray(Expr $expr): bool
    {
        if (! $expr instanceof MethodCall) {
            return $expr instanceof Array_
                || $this->isParentCallTo($expr, $this->methodName)
                || ($expr instanceof FuncCall && $this->mergedArrayLiteral($expr, $this->methodName) !== null);
        }

        if (! $expr->name instanceof Identifier) {
            return false;
        }

        $name = $expr->name->toString();
        $filterKeys = $this->filtersOwnModel($expr)
            ? $this->extractFilterKeys($expr, new ReflectionMethod(Model::class, $name))
            : null;

        return $filterKeys !== null
            || ($this->hasThisReceiver($expr) && $this->scope->subjectReflection->hasMethod($name));
    }

    /**
     * The class under analysis, the subject a warning names.
     *
     * @return ReflectionClass<object>
     */
    protected function subjectReflection(): ReflectionClass
    {
        return $this->scope->subjectReflection;
    }

    /**
     * A new, empty resource analysis for a returned variable's walk to fill.
     */
    protected function newVariableAnalysis(): ResourceAnalysis
    {
        return new ResourceAnalysis;
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
        $this->collectInstanceofGuards($stmts, $this->scope);
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

            // Handle ...$this->only([...]) or ...$this->except([...]) spread, or ...$this->resource->only([...])
            if ($item->key === null && $item->unpack
                && $item->value instanceof MethodCall
                && $this->filtersOwnModel($item->value)) {
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
                $spreadAnalysis = $this->analyzeThisMethodSpread($item->value->name->toString(), $topLevel, $item->value);

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
                        $spreadAnalysis = $this->analyzeThisMethodSpread($funcName, $topLevel, $item->value);

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

                $this->dropKeysSetBefore($mergeResult, $analysis);
                $analysis->merge($mergeResult);

                continue;
            }

            if ($item->key === null) {
                continue;
            }

            $keyName = $this->resolveKeyName($item->key, $this->scope->subjectReflection);

            if ($keyName === null) {
                continue;
            }

            $result = $this->analyzeValueExpression($item->value);

            // A key a signature covers is one more runtime key, so it replaces no earlier entry.
            if (JsEmitter::isIndexSignatureKey($keyName)) {
                $analysis->addProperty($keyName, ValueResult::asIndexSignatureValue($result));

                continue;
            }

            // When a child key overrides a parent spread key, clear stale parent tracking
            $analysis->forgetChannels($keyName);

            $analysis->addProperty($keyName, $result);
        }

        resolve(IndexSignatureReconciler::class)->reconcile($analysis);

        return $analysis;
    }

    /**
     * Drop each named key a merge sets that the array already holds, a signature excepted.
     *
     * Laravel's mergeData() unions the keys before a merge with the merged ones, so the earlier key keeps its value and
     * its presence.
     */
    private function dropKeysSetBefore(ResourceAnalysis $merged, ResourceAnalysis $before): void
    {
        $held = array_flip(array_column($before->properties, 'name'));
        $kept = [];

        foreach ($merged->properties as $property) {
            if (isset($held[$property['name']]) && ! JsEmitter::isIndexSignatureKey($property['name'])) {
                $merged->forgetChannels($property['name']);

                continue;
            }

            $kept[] = $property;
        }

        $merged->properties = $kept;
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
     * Flatten a spread model's toArray() into one property per published column and appended accessor, typed with
     * the channels ThisPropertyHandler carries for a `$this->column` access, honoring that model's own #[TsCasts]
     * overrides — the same refinement the canonical model interface a bare-model arm references gets.
     *
     * @param  class-string<Model>  $modelFqcn
     */
    private function analyzeModelSpreadArm(string $modelFqcn): ResourceAnalysis
    {
        DependencyRecorder::recordClass($modelFqcn);

        $resolver = resolve(ModelAttributeResolver::class);
        $tsCasts = $this->parseTsCastsFromReflection(new ReflectionClass($modelFqcn));
        $analysis = new ResourceAnalysis;

        // toArray() is columns plus $appends (accessor attributes explicitly opted into
        // serialization) — a mutator with no $appends entry never reaches it, so stays out.
        $appends = $resolver->getInstance($modelFqcn)?->getAppends() ?? [];
        $names = [...$resolver->publishedColumnNames($modelFqcn), ...$appends];

        foreach ($names as $column) {
            $override = $tsCasts['overrides'][$column] ?? null;
            $optional = $tsCasts['optionalOverrides'][$column] ?? false;

            if ($override !== null) {
                $customImports = [];
                $importPath = $tsCasts['importPaths'][$column] ?? null;

                if ($importPath !== null) {
                    $importable = TsTypeString::extractImportableTypes($override);

                    // A type with no importable token (e.g. `Record<string, unknown>`) must not
                    // materialise an empty list under its path.
                    if ($importable !== []) {
                        $customImports[$importPath] = $importable;
                    }
                }

                $analysis->addProperty($column, [
                    'type' => $override,
                    'optional' => $optional,
                    ...($customImports !== [] ? ['customImports' => $customImports] : []),
                ]);

                continue;
            }

            $tsInfo = $resolver->resolveAttribute($modelFqcn, $column, $this->scope->carriesImports);

            // Mirrors ModelTransformer::resolveMutatorType()'s own omit check: no getter, no
            // docblock generic, no backing column — nothing to publish for this name.
            if ($tsInfo['omit'] ?? false) {
                continue;
            }

            $analysis->addProperty($column, ValueResult::withAttributeChannels(['type' => $tsInfo['type'], 'optional' => $optional], $tsInfo));
        }

        return $analysis;
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
        $analysis = new ResourceAnalysis;

        $analysis->addProperty($indexKey, [
            'type' => TsNaming::resourceTypeName($modelFqcn),
            'optional' => false,
            'modelFqcn' => $modelFqcn,
        ]);

        return $analysis;
    }

    /**
     * Analyze an `array_merge(...)` returned directly or through a variable, as the array-literal it is equivalent to.
     *
     * Declines the whole call when an argument is neither a literal nor `parent::{$this->methodName}()`.
     */
    protected function analyzeReturnArrayMerge(FuncCall $call, bool $topLevel = true): ?ResourceAnalysis
    {
        $merged = $this->mergedArrayLiteral($call, $this->methodName);

        return $merged === null ? null : $this->analyzeReturnArray($merged, $topLevel);
    }

    /**
     * The ordered handler chain: the injected profile when one was supplied, else the subject's own, which
     * ResourceExpressionHandlers::forSubject() picks — see that class for the lists and their ordering contract.
     *
     * @return list<ExpressionHandler>
     */
    protected function handlers(): array
    {
        return $this->handlerProfile
            ?? ResourceExpressionHandlers::forSubject($this->scope->subjectReflection->getName(), $this);
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
        $lenientReads = $this->lenientReads;
        $result = $this->dispatcher()->dispatch($expr, $this->scope, $this) ?? ValueResult::unknown();

        // A value read partly types its own key and hides none, so it never makes a variable unreadable.
        $this->lenientReads = $lenientReads;

        return $result;
    }

    /**
     * Analyze $this->merge(...), mergeWhen(...) or mergeUnless(...) with each array the call can merge as a branch.
     *
     * A failed mergeWhen()/mergeUnless() condition merges the default when one is passed, else nothing, so a key only
     * some branches set publishes optional, and a key every branch sets is required. Their types union as a ternary's
     * arms do, leaving out a side the engine cannot type.
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
        $args = CallArguments::for($call, new ReflectionMethod(JsonResource::class, $method));
        $value = $args->named('value');

        if ($value === null) {
            return new ResourceAnalysis;
        }

        // mergeWhen() merges its value only where the condition holds, and mergeUnless() only where it fails.
        $condition = $args->named('condition')?->value;
        $branches = $this->resolveProvenNonNull(
            $condition === null ? [] : $this->nonNullReads($condition, $isMergeWhen),
            $this->scope,
            fn (): array => $this->resolveMergedBranches($value->value),
        );

        if (! $isMerge) {
            $default = $args->named('default')?->value;

            // Laravel calls a default closure with no argument, so one requiring an argument never merges an array.
            $defaultBranches = $default === null || $this->closureRequiresArguments($default)
                ? []
                : $this->resolveMergedBranches($default);

            // A side read as no array stands as an empty branch, like the MissingValue an omitted default leaves.
            $branches = [
                ...($branches === [] ? [new ResourceAnalysis] : $branches),
                ...($defaultBranches === [] ? [new ResourceAnalysis] : $defaultBranches),
            ];
        }

        return match (count($branches)) {
            0 => new ResourceAnalysis,
            1 => $branches[0],
            default => $this->mergeReturnBranches($branches, dropsUntypedBranches: true, keepsLoneNull: true),
        };
    }

    /**
     * The arrays a merge argument can merge, one analysis each: a literal, a value, or each array a closure returns.
     *
     * mergedValueAnalysis() reads a value; an argument the analysis cannot read counts as a lenient read.
     *
     * @return list<ResourceAnalysis> empty when the argument merges no array the analysis reads
     */
    protected function resolveMergedBranches(Expr $expr): array
    {
        if ($expr instanceof Array_) {
            return [$this->mergedArrayAnalysis($expr)];
        }

        // Laravel merges value($value), so a value passed as it is merges as a closure returning it would.
        if (! $expr instanceof ClosureExpr && ! $expr instanceof ArrowFunction) {
            $branch = $this->mergedValueBranch($expr);

            if ($branch === null) {
                $this->lenientReads++;
            }

            return $branch === null ? [] : [$branch];
        }

        // merge()/mergeWhen() call their closure with no argument: each parameter owns its name and holds its default.
        $previousNameBindings = $this->scope->nameBindings();

        try {
            $this->scope->claimParameters($expr);
            $this->scope->bindUnpassedParameters($expr, 0, $this);
            $this->proveClosureGuards($expr, $this->scope);

            return $this->closureReturnBranches($expr);
        } finally {
            $this->scope->restoreNameBindings($previousNameBindings);
        }
    }

    /**
     * The keys one merged array literal sets, each required within its own branch.
     */
    private function mergedArrayAnalysis(Array_ $array): ResourceAnalysis
    {
        return (new ThisPropertyHandler)
            ->extractPropertiesFromArray($array, $this, $this->scope->subjectReflection, optional: false);
    }

    /**
     * The keys a merged model or method call sets.
     *
     * The resource's own model merges what `Model::toArray()` writes, through jsonSerialize(); any other value is read
     * as a whole array analyzeArrayExpression() accepts.
     */
    private function mergedValueAnalysis(Expr $expr): ?ResourceAnalysis
    {
        // A collection's resource is the list of collected items, whose keys are numbers.
        if ($this->isResourceFetch($expr)) {
            return $this->isResourceCollection($this->scope) ? null : $this->buildModelSerializedAnalysis();
        }

        return $this->analyzeArrayExpression($expr, topLevel: false);
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
            return $this->methodName === 'toArray' ? $this->buildModelSerializedAnalysis() : null;
        }

        $parentAnalyzer = new self(
            $parentClass,
            $this->scope->modelClass,
            $this->methodName,
            $this->handlerProfile,
            carriesImports: $this->scope->carriesImports,
        );
        $parentAnalyzer->sharedDataShareClass = $this->sharedDataShareClass();

        $analysis = $parentAnalyzer->analyze();
        $this->lenientReads += $parentAnalyzer->lenientReads;

        return $analysis;
    }

    /**
     * Resolve and analyze a $this->method() spread; $topLevel carries the caller's own flatten-eligibility down into
     * the target's own return (see analyzeReturnArray()), and $call places the guard proofs that hold for it. Each
     * binding table it clears is restored in a `finally`.
     */
    protected function analyzeThisMethodSpread(string $methodName, bool $topLevel = true, ?Expr $call = null): ?ResourceAnalysis
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

        $previousLocalVarBindings = $this->scope->localVarBindings;
        $previousResolvingLocalVars = $this->scope->resolvingLocalVars;
        $previousVarModelBindings = $this->scope->varModelBindings;
        $previousVarClassBindings = $this->scope->varClassBindings;
        $previousVarGuardBindings = $this->scope->varGuardBindings;
        $previousVarDocBindings = $this->scope->varDocBindings;
        $previousNonNullReads = $this->scope->nonNullReads;
        $previousDeclaringFileClass = $this->scope->declaringFileClass;
        $previousRequestVarNames = $this->scope->requestVarNames;
        try {
            $this->scope->localVarBindings = [];
            $this->scope->resolvingLocalVars = [];
            $this->scope->varModelBindings = [];
            $this->scope->varClassBindings = [];
            $this->scope->varGuardBindings = [];
            $this->scope->varDocBindings = [];
            $this->scope->nonNullReads = $this->proofsAcrossCall($this->scope, $call);
            $this->scope->declaringFileClass = LaravelTsPublish::methodDeclaringFileClass($method);
            // The spread method has its own signature: the entry method's Request params say nothing
            // about which of ITS variables hold one. analyzeParentToArray() re-derives the same way.
            $this->scope->requestVarNames = $this->resolveRequestVarNames($methodName);
            $this->seedVarBindings($targetMethod->stmts);

            $branches = [];

            foreach ($this->collectReturnExpressions($targetMethod->stmts) as $returned) {
                $branches[] = match (true) {
                    $returned instanceof Array_ && $returned->items === [] => new ResourceAnalysis,
                    $returned instanceof Array_ => $this->analyzeReturnArray($returned, $topLevel),
                    $returned instanceof Variable && is_string($returned->name) => $this->resolveVariableReturnAnalysis($targetMethod->stmts, $returned->name, $topLevel),
                    default => null,
                };
            }

            // Every branch classified: each is a shape the method can return, so a key missing from
            // one publishes optional. Anything unclassifiable falls back to the first-return path.
            $analysis = $branches !== [] && ! in_array(null, $branches, true)
                ? (count($branches) === 1 ? $branches[0] : $this->mergeReturnBranches($branches, dropsUntypedBranches: true))
                : $this->analyzeFirstReturn($targetMethod->stmts, $topLevel);
        } finally {
            $this->scope->localVarBindings = $previousLocalVarBindings;
            $this->scope->resolvingLocalVars = $previousResolvingLocalVars;
            $this->scope->varModelBindings = $previousVarModelBindings;
            $this->scope->varClassBindings = $previousVarClassBindings;
            $this->scope->varGuardBindings = $previousVarGuardBindings;
            $this->scope->varDocBindings = $previousVarDocBindings;
            $this->scope->nonNullReads = $previousNonNullReads;
            $this->scope->declaringFileClass = $previousDeclaringFileClass;
            $this->scope->requestVarNames = $previousRequestVarNames;
            unset($this->scope->visitedSpreadMethods[$methodName]);
        }

        resolve(ReturnShapeRefiner::class)->refine($analysis, $method);
        $this->applyTsCastsFromMethod($method, $analysis);

        // The refiner and #[TsCasts] can each change a key after the merge above reconciled it.
        resolve(IndexSignatureReconciler::class)->reconcile($analysis);

        return $analysis;
    }

    /**
     * The method's own first return, never a closure's: the fallback whenever the branch sweep above cannot classify
     * every return a method makes, so it is always a lenient read.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function analyzeFirstReturn(array $stmts, bool $topLevel = true): ResourceAnalysis
    {
        $this->lenientReads++;

        $returned = $this->collectReturnExpressions($stmts)[0] ?? null;

        if ($returned instanceof Array_) {
            $analysis = $this->analyzeReturnArray($returned, $topLevel);
        } elseif ($returned instanceof Variable && is_string($returned->name)) {
            $analysis = $this->resolveVariableReturnAnalysis($stmts, $returned->name, $topLevel);
        } elseif ($returned instanceof MethodCall) {
            $filtered = $this->analyzeThisAttributeFilter($returned);

            if ($filtered !== null) {
                $analysis = $filtered;
            } elseif ($this->hasThisReceiver($returned) && $returned->name instanceof Identifier) {
                $analysis = $this->analyzeThisMethodSpread($returned->name->toString(), $topLevel, $returned) ?? new ResourceAnalysis;
            } else {
                $analysis = new ResourceAnalysis;
            }
        } else {
            $analysis = new ResourceAnalysis;
        }

        return $analysis;
    }

    /**
     * The class declaring the share() InertiaSharedDataAnalyzer reads the casts of, own or inherited by the analyzed
     * middleware; an empty string for any other subject.
     */
    private function sharedDataShareClass(): string
    {
        return $this->sharedDataShareClass ??= $this->methodName === 'share'
            && is_subclass_of($this->scope->subjectReflection->getName(), 'Inertia\\Middleware')
                ? $this->scope->subjectReflection->getMethod('share')->getDeclaringClass()->getName()
                : '';
    }

    /**
     * Whether the method is the share() whose casts InertiaSharedDataAnalyzer applies, never a parent's one that the
     * middleware's own share() spreads.
     */
    private function isInertiaShare(ReflectionMethod $method): bool
    {
        return $method->getDeclaringClass()->getName() === $this->sharedDataShareClass();
    }

    /**
     * Apply #[TsCasts] overrides declared on a reflection method, updating or injecting properties.
     *
     * Accepted on trait/helper methods and on toArray() itself, as a lightweight override mechanism.
     */
    private function applyTsCastsFromMethod(ReflectionMethod $method, ResourceAnalysis $analysis): void
    {
        foreach ($method->getAttributes(TsCasts::class) as $attr) {
            $types = JsEmitter::castsByKey($attr->newInstance()->types, array_column($analysis->properties, 'name'));

            foreach ($types as $property => $value) {
                $type = is_array($value) ? $value['type'] : $value;
                $optional = is_array($value) && isset($value['optional']) ? (bool) $value['optional'] : null;
                $import = is_array($value) ? ($value['import'] ?? null) : null;

                if ($optional === true) {
                    ['type' => $type, 'optional' => $optional] = JsEmitter::signatureSafeMember((string) $property, $type, true);
                }

                $found = false;

                // Every entry of a key written twice: the last one publishes, and the reconciler reads them all.
                foreach ($analysis->properties as &$prop) {
                    if ($prop['name'] === $property) {
                        $prop['type'] = $type;
                        unset($prop['bodyType']);

                        if ($optional !== null) {
                            $prop['optional'] = $optional;
                        }

                        $found = true;
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

                // The publisher fits the key's import channels to this text once it knows which cast is in force.
                $analysis->casts[$property] = $optional === null
                    ? ['type' => $type, 'import' => $import]
                    : ['type' => $type, 'import' => $import, 'optional' => $optional];

                if ($import !== null) {
                    foreach (TsTypeString::extractImportableTypes($type) as $importName) {
                        $analysis->customImports[$import][] = $importName;
                    }
                }
            }
        }
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

        $elementType = $this->wrapCollectionElementType(TsNaming::resourceTypeName($singular), $this->scope->subjectReflection);

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
     * Analyze all direct Return_ statements yielding an Array_ literal or a variable, merging multiple branches with
     * union semantics: properties present in only some become optional.
     *
     * @param  array<Node\Stmt>  $stmts
     */
    protected function analyzeAllReturnBranches(array $stmts): ?ResourceAnalysis
    {
        /** @var list<Return_> $candidates */
        $candidates = [];

        $this->collectDirectReturns($stmts, $candidates);

        // A variable the walk does not read completely is skipped like any other non-literal return, so its unread keys
        // never turn a sibling's optional.
        /** @var array<string, ResourceAnalysis|null> $variables */
        $variables = [];

        foreach ($candidates as $return) {
            if ($return->expr instanceof Variable && is_string($return->expr->name)
                && ! array_key_exists($return->expr->name, $variables)) {
                $variables[$return->expr->name] = $this->variableBranch($stmts, $return->expr->name);
            }
        }

        $returns = count($this->collectReturnExpressions($stmts));
        $literalHasItems = array_any(
            $candidates,
            fn (Return_ $return): bool => $return->expr instanceof Array_ && $return->expr->items !== [],
        );
        $variableHasItems = array_any(
            $variables,
            fn (?ResourceAnalysis $branch): bool => $branch !== null && $branch->properties !== [],
        );

        // With no key read completely and every return a literal or a variable, a skipped variable is read leniently
        // instead, so a lone one still gets analyze()'s refiner and casts. A return of any other kind is read by the
        // first-return fallback, as before.
        $lenient = ! $literalHasItems && ! $variableHasItems && count($candidates) === $returns;

        if ($lenient) {
            foreach ($variables as $name => $branch) {
                $variables[$name] = $branch ?? $this->resolveVariableReturnAnalysis($stmts, $name);
            }
        }

        /** @var list<Array_|ResourceAnalysis> $branches */
        $branches = [];
        $taken = 0;

        foreach ($candidates as $return) {
            $expr = $return->expr;
            $branch = match (true) {
                $expr instanceof Array_ => $expr,
                $expr instanceof Variable && is_string($expr->name) => $variables[$expr->name],
                default => null,
            };

            if ($branch === null) {
                continue;
            }

            $taken++;

            if (! in_array($branch, $branches, true)) {
                $branches[] = $branch;
            }
        }

        // A `return []` guard is a branch like any other: the keys its siblings set are absent on that path, so they
        // publish optional. Without a key read completely, only a lenient read of a variable keeps the sweep going.
        $proceeds = $literalHasItems || $variableHasItems || ($lenient && $variables !== []);

        // A return no branch stands for, or one the first-return fallback leaves beside the one it reads, is unread.
        // The published shape stays, but a child that builds on it cannot trust its key set.
        if ($proceeds ? $taken < $returns : $returns > 1) {
            $this->lenientReads++;
        }

        if (! $proceeds) {
            return null;
        }

        $analyses = array_map(fn (Array_|ResourceAnalysis $branch): ResourceAnalysis => match (true) {
            $branch instanceof ResourceAnalysis => $branch,
            $branch->items === [] => new ResourceAnalysis,
            default => $this->analyzeReturnArray($branch),
        }, $branches);

        return count($analyses) === 1 ? $analyses[0] : $this->mergeReturnBranches($analyses);
    }

    /**
     * Merge branch analyses: a key some branch lacks is optional, channels merge as MethodAnalysis::merge() does, a
     * cast survives only where every branch setting its key casts it alike, and flatTypeAlias is the first non-null.
     *
     * Public for `Inertia::render()`. A spread helper and a merge call drop an untyped branch ($dropsUntypedBranches);
     * only a merge call keeps the `null` left alone ($keepsLoneNull).
     *
     * @param  list<ResourceAnalysis>  $analyses
     */
    public function mergeReturnBranches(
        array $analyses,
        bool $dropsUntypedBranches = false,
        bool $keepsLoneNull = false,
    ): ResourceAnalysis {
        $branchCount = count($analyses);

        /** @var array<string, list<AnalyzedProperty>> */
        $propertyMap = [];

        // Only the channels collapse onto merge(): a property name several branches set has to be
        // union-typed from $propertyMap below, which a field-by-field merge cannot express.
        $channels = new ResourceAnalysis;
        $flatTypeAlias = null;
        $flatTypeAliasFqcn = null;

        foreach ($analyses as $analysis) {
            foreach ($analysis->properties as $prop) {
                $propertyMap[$prop['name']][] = $prop;
            }

            $channels->merge($analysis);
            $channels->properties = [];

            $flatTypeAlias ??= $analysis->flatTypeAlias;
            $flatTypeAliasFqcn ??= $analysis->flatTypeAliasFqcn;
        }

        /** @var AnalyzedPropertyList */
        $properties = [];

        foreach ($propertyMap as $name => $entries) {
            $type = $this->branchUnion(array_column($entries, 'type'), $dropsUntypedBranches, $keepsLoneNull);
            $bodyTypes = array_map(fn (array $e): string => $e['bodyType'] ?? $e['type'], $entries);
            $bodyType = $this->branchUnion($bodyTypes, $dropsUntypedBranches, $keepsLoneNull);

            $presentInAll = count($entries) === $branchCount;
            $anyOptional = (bool) array_filter($entries, fn (array $e) => $e['optional']);
            // An index signature (e.g. `[key: number]`) can never carry `?:` — it's a syntax
            // error — regardless of whether every branch produced it.
            $optional = ! JsEmitter::isIndexSignatureKey($name) && (! $presentInAll || $anyOptional);

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
                ...($bodyType === $type ? [] : ['bodyType' => $bodyType]),
            ];
        }

        return new ResourceAnalysis(
            properties: $properties,
            enumResources: $channels->enumResources,
            nestedResources: $channels->nestedResources,
            customImports: $channels->customImports,
            directEnumFqcns: $channels->directEnumFqcns,
            modelFqcns: $channels->modelFqcns,
            inlineEnumFqcns: $channels->inlineEnumFqcns,
            inlineModelFqcns: $channels->inlineModelFqcns,
            inlineResourceFqcns: $channels->inlineResourceFqcns,
            multiEnumResourceFqcns: $channels->multiEnumResourceFqcns,
            inlineEnumResourceFqcns: $channels->inlineEnumResourceFqcns,
            enumResourceArmShapes: $channels->enumResourceArmShapes,
            casts: $this->castsEveryBranchAgrees($analyses, array_keys($propertyMap)),
            carried: $channels->carried,
            flatTypeAlias: $flatTypeAlias,
            flatTypeAliasFqcn: $flatTypeAliasFqcn,
        );
    }

    /**
     * The cast entry of each key every branch that sets it casts to one text and import, as only then does the union
     * publish that cast; it keeps an `optional` flag only where every branch's cast sets the same one.
     *
     * @param  list<MethodAnalysis>  $analyses
     * @param  list<array-key>  $names
     * @return CastMap
     */
    private function castsEveryBranchAgrees(array $analyses, array $names): array
    {
        $casts = [];

        foreach ($names as $name) {
            $branchCasts = [];

            foreach ($analyses as $analysis) {
                if (in_array((string) $name, array_column($analysis->properties, 'name'), true)) {
                    $branchCasts[] = $analysis->casts[$name] ?? null;
                }
            }

            $cast = $branchCasts[0] ?? null;

            if ($cast === null || ! array_all($branchCasts, static fn (?array $branchCast): bool => $branchCast !== null
                && $branchCast['type'] === $cast['type'] && $branchCast['import'] === $cast['import'])) {
                continue;
            }

            $casts[(string) $name] = array_all($branchCasts, static fn (?array $branchCast): bool => $branchCast === $cast)
                ? $cast
                : ['type' => $cast['type'], 'import' => $cast['import']];
        }

        return $casts;
    }

    /**
     * One property's branch types as one type: the type itself when every branch agrees.
     *
     * @param  list<string>  $types
     */
    private function branchUnion(array $types, bool $dropsUntypedBranches, bool $keepsLoneNull): string
    {
        $unique = array_values(array_unique($types));

        if ($dropsUntypedBranches && count($unique) > 1) {
            $typed = array_values(array_diff($unique, ['unknown']));

            // A lone `null` left once the untypable branches are gone says nothing about a spread helper's value, but
            // a merge call keeps it, as a ternary keeps its one typed arm.
            $unique = $typed === [] || ($typed === ['null'] && ! $keepsLoneNull) ? ['unknown'] : $typed;
        }

        return count($unique) === 1 ? $unique[0] : $this->unionBranchTypes($unique);
    }

    /**
     * Union branch type strings, hoisting one trailing `| null` rather than repeating the marker each
     * nullsafe branch already carries. Only top-level nulls move; a nested one belongs to its member.
     *
     * @param  list<string>  $types
     */
    private function unionBranchTypes(array $types): string
    {
        // `unknown` absorbs whatever it is unioned with, so one untypable branch makes the whole
        // union `unknown` — never a `string` that the untypable branch does not actually promise.
        if (in_array('unknown', $types, true)) {
            return 'unknown';
        }

        return TsTypeString::hoistNull($types);
    }

    /**
     * Recursively collect Return_ statements with Array_ or Variable expressions from
     * the given statements, descending into control-flow structures (if, foreach, etc.)
     * but NOT into closures, arrow functions, or anonymous classes.
     *
     * @param  array<Node\Stmt|Node>  $stmts
     * @param  list<Return_>  $candidates
     */
    protected function collectDirectReturns(array $stmts, array &$candidates): void
    {
        foreach ($stmts as $stmt) {
            if ($stmt instanceof Return_ && ($stmt->expr instanceof Array_ || $stmt->expr instanceof Variable)) {
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
