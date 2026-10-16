<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Analyzers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\BuildsJsonApiResourceObject;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\FiltersModelAttributes;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ResolvesModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNonNullGuards;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsReturnedVariables;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ExpressionDispatcher;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\FirstClassCallableHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\InlineArrayHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Handlers\ThisPropertyHandler;
use AbeTwoThree\LaravelTsPublish\Ast\IndexSignatureReconciler;
use AbeTwoThree\LaravelTsPublish\Ast\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Ast\MethodAnalysis;
use AbeTwoThree\LaravelTsPublish\Ast\MethodContext;
use AbeTwoThree\LaravelTsPublish\Ast\MethodLocator;
use AbeTwoThree\LaravelTsPublish\Ast\ParameterBinder;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverMethodReturnResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceExpressionHandlers;
use AbeTwoThree\LaravelTsPublish\Ast\ReturnShapeRefiner;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectPropertyTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
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
use AbeTwoThree\LaravelTsPublish\Support\ResourceReindexing;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrowFunction;
use PhpParser\Node\Expr\BinaryOp\Plus;
use PhpParser\Node\Expr\BooleanNot;
use PhpParser\Node\Expr\CallLike;
use PhpParser\Node\Expr\Closure as ClosureExpr;
use PhpParser\Node\Expr\FuncCall;
use PhpParser\Node\Expr\Instanceof_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use PhpParser\Node\Scalar\String_;
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

/**
 * Analyzes a JsonResource's toArray() body to extract property names, types, and optional markers via AST.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type AnalyzedProperty from MethodAnalysis
 * @phpstan-import-type AnalyzedPropertyList from MethodAnalysis
 * @phpstan-import-type CastMap from MethodAnalysis
 * @phpstan-import-type PassedBinding from ParameterBinder
 *
 * @internal
 */
class ResourceAstAnalyzer implements ExpressionEngine
{
    use BuildsJsonApiResourceObject;
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

    /** Laravel's own methods whose bodies answer from the runtime class, which the analyzer reads in their place. */
    private const array FRAMEWORK_READS = ['jsonSerialize', 'resolve', 'with'];

    /** Carries the subject reflection, model class, and all closure/spread bindings; see AnalysisScope. */
    protected AnalysisScope $scope;

    /** Built once per instance by dispatcher(), so the handler-candidate memo survives across dispatches. */
    protected ?ExpressionDispatcher $dispatcher = null;

    /** The class declaring the share() whose casts InertiaSharedDataAnalyzer applies; a parent's analyzer keeps it. */
    protected ?string $sharedDataShareClass = null;

    /** Whether this analyzer built its scope, so the analyzed method's parameters are its own to bind. */
    private readonly bool $ownsScope;

    /** Whether the handler profile invokes callables, read once from it by invokesCallables(). */
    private ?bool $invokingProfile = null;

    /** @var array<string, true> the runtime class's methods whose reads enclose this one, so a read of one re-enters */
    private array $methodsOnStack = [];

    /** Whether the analyzed method's return is read as its whole value, which takes the collected list flat. */
    private bool $returnedWhole = true;

    /**
     * Create an analyzer for a class, its optional backing model, and the method to analyze.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resourceReflection  templated because ReflectionClass is invariant
     * @param  class-string<Model>|null  $modelClass
     * @param  list<ExpressionHandler>|null  $handlerProfile  overrides the subject's own profile
     * @param  AnalysisScope|null  $scope  a scope AstEngine::bindingsFor() seeded; it gains only the collection facts
     * @param  MethodContext|null  $context  a context already located for $methodName, used instead of locating one
     * @param  bool  $carriesImports  seeds AnalysisScope::$carriesImports; a supplied scope keeps its own
     * @param  array<string, PassedBinding>  $passed  what a call passes the method's parameters, bound with its scope
     * @param  ReflectionClass<object>|null  $runtimeClass  the class `$this` is at run time when this reads an
     *                                                      ancestor's body for it; null when that is the subject
     */
    public function __construct(
        protected ReflectionClass $resourceReflection,
        protected ?string $modelClass = null,
        protected string $methodName = 'toArray',
        protected ?array $handlerProfile = null,
        ?AnalysisScope $scope = null,
        protected ?MethodContext $context = null,
        bool $carriesImports = true,
        private readonly array $passed = [],
        private readonly ?ReflectionClass $runtimeClass = null,
    ) {
        $this->scope = $scope ?? new AnalysisScope(
            self::genericReflection($this->resourceReflection->getName()),
            $this->modelClass,
        );

        if ($scope === null) {
            $this->scope->carriesImports = $carriesImports;
        }

        // The runtime class alone decides these, so a supplied scope or an ancestor's body reads its items as it does.
        $this->scope->seedCollectionFacts($this->runtimeClass());

        $this->ownsScope = $scope === null;

        if ($this->scope->modelClass !== null) {
            $this->loadModelInspectorData();
        }
    }

    /**
     * The runtime class: the class `$this` is when the body runs, whose collects(), `$wrap` and keys every ancestor
     * body it runs reads. The subject, unless this analyzer reads an ancestor's body for a subclass.
     *
     * @return ReflectionClass<object>
     */
    private function runtimeClass(): ReflectionClass
    {
        return $this->runtimeClass ?? $this->scope->subjectReflection;
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
     * Whether this analysis reads a collection's toArray(), and the subject's toArray() sends the collected list.
     */
    private function toArraySendsCollectedList(): bool
    {
        return $this->readsCollectionToArray()
            && resolve(ResourceCollectionBody::class)->sendsCollectedList($this->scope->subjectReflection);
    }

    /**
     * Whether this analysis reads a collection's toArray() and the body returns its collected list whole.
     */
    private function toArrayReturnsCollectedList(Expr $returned): bool
    {
        return $this->readsCollectionToArray()
            && resolve(ResourceCollectionBody::class)->returnsCollectedList($returned, $this->scope->subjectReflection);
    }

    /**
     * Whether the analyzed method is a resource collection's toArray(), the one whose collected list Laravel wraps.
     */
    private function readsCollectionToArray(): bool
    {
        return $this->methodName === 'toArray' && $this->isResourceCollection($this->scope);
    }

    /**
     * Whether this analysis reads the response of a plain resource that runs as itself and overrides toAttributes(),
     * which resolve() sends in toArray()'s place once JsonResource declares it; a collection's items are no attributes.
     */
    private function sendsOwnToAttributes(): bool
    {
        $runtime = $this->runtimeClass();

        return $this->methodName === 'toArray'
            && $this->context === null
            && $runtime->getName() === $this->scope->subjectReflection->getName()
            && JsonApiResources::shipped()
            && $runtime->isSubclassOf(JsonResource::class)
            && ! JsonApiResources::declaredByFramework($runtime->getMethod('toAttributes')->getDeclaringClass()->getName())
            && ! $this->isResourceCollection($this->scope);
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
     * Rewrite each property type to the list a resource sends a numeric-keyed array as, unless the resource preserves
     * keys; a key a method cast types keeps the cast's text, and a value the filter never enters keeps its keys.
     *
     * The publishing class decides, not the one declaring the body: an inherited toArray() runs under the subclass's
     * own `$preserveKeys`.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $resource
     */
    public static function reindexNumericKeyedArrays(MethodAnalysis $analysis, ReflectionClass $resource): void
    {
        if (ResourceCollections::reindexesNumericKeys($resource)) {
            $analysis->rewritePropertyTypes(ResourceReindexing::reindexedType(...));
        }
    }

    /**
     * Analyze the subject's $this->methodName body and return the resulting property/type analysis.
     */
    public function analyze(): ResourceAnalysis
    {
        if ($this->scope->modelClass !== null) {
            DependencyRecorder::recordClass($this->scope->modelClass);
        }

        // A JSON:API resource sends its resource object as `data`, whatever toArray() returns: toArray() feeds at most
        // its attributes.
        if ($this->sendsJsonApiResourceObject()) {
            return $this->buildJsonApiResourceObject();
        }

        if ($this->sendsOwnToAttributes()) {
            return $this->analyzeThisMethodSpread('toAttributes') ?? new ResourceAnalysis;
        }

        $context = $this->context ?? $this->locateSubjectMethod();
        $toArrayMethod = $context?->method;

        // Wherever the toArray() that sends the collected list is declared, Laravel's collects(), `$wrap` and keys read
        // static::class, so the response is the subject's own body-less one.
        if ($this->toArraySendsCollectedList()) {
            $response = $this->buildCollectionDelegatedAnalysis();

            return $toArrayMethod?->stmts === null ? $response : $this->ownMethodShaped($response);
        }

        if ($toArrayMethod === null || $toArrayMethod->stmts === null) {
            $inherited = $this->analyzeParentCall($this->methodName, $this->returnedWhole);

            // An empty result means no ancestor declared the method either, so keep delegating.
            if ($inherited !== null && ($inherited->properties !== [] || $inherited->flatTypeAlias !== null)) {
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

            $delegated = $this->buildModelSerializedAnalysis();

            // With no model behind it, the delegation publishes none of the keys the response carries.
            if ($delegated === null) {
                $this->lenientReads++;
            }

            return $delegated ?? new ResourceAnalysis;
        }

        $finder = new NodeFinder;

        // A guard on `$this->resource` narrows only the proxy; in any other subject it tests a member like any other.
        $this->scope->instanceOfWrappedClass = $this->scope->hasResourceProxy() ? $this->resolveInstanceOfType($toArrayMethod, $finder) : null;

        // Re-derived once an instanceof guard has supplied a backing the constructor had none for. Not rebase(): the
        // guard's class, model or not, backs only the proxy, while modelClass stays the resolved backing model.
        $this->scope->forwardsUndeclaredMembersTo = AnalysisScope::proxyTargetFor(
            $this->scope->subjectReflection,
            $this->scope->modelClass ?? $this->scope->instanceOfWrappedClass,
        );

        // A scope AstEngine::bindingsFor() seeded already holds the method's parameters and its body's own bindings,
        // but no foreach variable over a relation, which bindForeachLoopVariables() binds only for a scope it owns.
        if ($this->ownsScope) {
            $subject = $this->scope->subjectReflection;
            $method = $subject->hasMethod($this->methodName) ? $subject->getMethod($this->methodName) : null;

            $this->seedVarBindings($method, $toArrayMethod->stmts, $this->passed);
        }

        $branchAnalysis = $this->analyzeAllReturnBranches($toArrayMethod->stmts);

        if ($branchAnalysis !== null) {
            return $this->ownMethodShaped($branchAnalysis);
        }

        // Fallback: the method's own first return for non-array returns (parent::toArray, $this->only, etc.); a
        // closure's return is the closure's value, never the method's.
        $returned = $this->collectReturnExpressions($toArrayMethod->stmts)[0] ?? null;

        if ($returned === null) {
            return new ResourceAnalysis; // @codeCoverageIgnore
        }

        $parentMethod = $this->readParentCall($returned);
        $frameworkMethod = $this->readThisFrameworkCall($returned);
        $analysis = match (true) {
            $returned instanceof Variable && is_string($returned->name) => $this->resolveVariableReturnAnalysis($toArrayMethod->stmts, $returned->name),
            $this->toArrayReturnsCollectedList($returned) => $this->buildCollectionDelegatedAnalysis(),
            $parentMethod !== null => $this->analyzeParentCall($parentMethod, $this->returnedWhole),
            $frameworkMethod !== null => $this->frameworkMethodAnalysis($frameworkMethod, $this->returnedWhole),
            default => $this->analyzeArrayExpression($returned),
        };

        if ($analysis === null) {
            $this->lenientReads++;
        }

        return $this->ownMethodShaped($analysis ?? new ResourceAnalysis);
    }

    /**
     * Lay the analyzed method's own `@return` shape and `#[TsCasts]` over what its body returns.
     */
    private function ownMethodShaped(ResourceAnalysis $analysis): ResourceAnalysis
    {
        if (! $this->scope->subjectReflection->hasMethod($this->methodName)) {
            return $analysis;
        }

        $ownMethod = $this->scope->subjectReflection->getMethod($this->methodName);

        resolve(ReturnShapeRefiner::class)->refine($analysis, $ownMethod, keepsUnresolvedNames: false);

        // InertiaSharedDataAnalyzer lays this share()'s casts over the props itself, with the docblock and `?`.
        if (! $this->isInertiaShare($ownMethod)) {
            $this->applyTsCastsFromMethod($ownMethod, $analysis);
        }

        resolve(IndexSignatureReconciler::class)->reconcile($analysis);

        return $analysis;
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
     * Whether this analyzer's handler profile reads a callable as the call it stands for, as Inertia's profiles do.
     */
    public function invokesCallables(): bool
    {
        return $this->invokingProfile ??= array_any(
            $this->handlers(),
            fn (ExpressionHandler $handler): bool => $handler instanceof FirstClassCallableHandler && $handler->invokesCallables(),
        );
    }

    /**
     * The analysis of a whole array a method returns or builds a variable from: a literal, a `parent::` call
     * readParentCall() reads, an `array_merge()` of those, an own-model `only()`/`except()`, a `$this->method()` call
     * or a `+` of any of them; null for any other expression.
     */
    protected function analyzeArrayExpression(Expr $expr, bool $topLevel = true): ?ResourceAnalysis
    {
        if ($expr instanceof Array_) {
            return $this->analyzeReturnArray($expr, $topLevel);
        }

        $parentMethod = $this->readParentCall($expr);

        if ($parentMethod !== null) {
            return $this->analyzeParentCall($parentMethod);
        }

        // array_merge(parent::share($request), [...]) — the shape a shared-data middleware writes.
        if ($expr instanceof FuncCall) {
            return $this->analyzeReturnArrayMerge($expr, $topLevel);
        }

        // `$a + $b` keeps every key $a sets, as `+=` does.
        if ($expr instanceof Plus) {
            $left = $this->readsAsArray($expr) ? $this->analyzeArrayExpression($expr->left, $topLevel) : null;
            $right = $left === null ? null : $this->analyzeArrayExpression($expr->right, $topLevel);

            if ($left === null || $right === null) {
                return null;
            }

            $right->dropKeysHeldBy($left);
            $left->merge($right);

            return $left;
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
        if ($expr instanceof Plus) {
            return $this->readsAsArray($expr->left) && $this->readsAsArray($expr->right);
        }

        if (! $expr instanceof MethodCall) {
            return $expr instanceof Array_
                || $this->readParentCall($expr) !== null
                || ($expr instanceof FuncCall
                    && $this->mergedArrayLiteral($expr, $this->scope->subjectReflection, $this->methodName) !== null);
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
     * Seed the scope from a method body: its parameters, by what each declares and what its call passes, the body's
     * own bindings, then the relation-typed foreach loop variables that only a model-backed scope can resolve.
     *
     * @param  array<Node\Stmt>  $stmts
     * @param  array<string, PassedBinding>  $passed
     */
    protected function seedVarBindings(?ReflectionMethod $method, array $stmts, array $passed = []): void
    {
        if ($method !== null) {
            resolve(ParameterBinder::class)->bindCall($method, $stmts, $passed, $this->scope);
        }

        $this->seedBody($stmts, $this->scope);
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
        // Laravel maps the keyless names of the array a JSON:API toAttributes() returns, never of one nested in it.
        $readsAttributeNames = $this->readsKeylessAttributeNames;
        $this->readsKeylessAttributeNames = false;

        try {
            $analysis = $this->analyzeReturnArrayItems($array, $topLevel, $readsAttributeNames);
        } finally {
            $this->readsKeylessAttributeNames = $readsAttributeNames;
        }

        resolve(IndexSignatureReconciler::class)->reconcile($analysis);

        return $analysis;
    }

    /**
     * Analyze the items of an array literal analyzeReturnArray() reads. With $readsAttributeNames, a keyless string
     * reads the model attribute it names, `$this->resource->{name}`, and `...parent::toAttributes()` what it returns.
     */
    private function analyzeReturnArrayItems(Array_ $array, bool $topLevel, bool $readsAttributeNames): ResourceAnalysis
    {
        $analysis = new ResourceAnalysis;

        foreach ($array->items as $item) {
            if ($readsAttributeNames && $item->key === null && ! $item->unpack && $item->value instanceof String_) {
                $analysis->forgetChannels($item->value->value);
                $analysis->addProperty($item->value->value, $this->analyzeValueExpression($this->modelAttributeRead($item->value->value)));

                continue;
            }

            if ($readsAttributeNames && $this->spreadsFrameworkToAttributes($item)) {
                $analysis->merge($this->jsonApiFrameworkAttributesAnalysis());

                continue;
            }

            // Handle a ...parent::toArray($request) spread, or of any parent:: call readParentCall() reads
            $parentMethod = $item->key === null && $item->unpack ? $this->readParentCall($item->value) : null;

            if ($parentMethod !== null) {
                $parentAnalysis = $this->analyzeParentCall($parentMethod);

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

            // ...$post->only([...]) on any other receiver holding one model flattens as the own-model spread does.
            if ($topLevel && $item->key === null && $item->unpack && $item->value instanceof MethodCall) {
                $filterAnalysis = $this->analyzeReceiverModelFilter($item->value);

                if ($filterAnalysis !== null) {
                    $analysis->merge($filterAnalysis);

                    continue;
                }
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

            // ...self::helper() or ...static::helper() spreads the subject's own method, as ...$this->helper() does.
            if ($item->key === null && $item->unpack
                && $item->value instanceof StaticCall
                && $item->value->class instanceof Name
                && in_array($item->value->class->toLowerString(), ['self', 'static'], true)
                && $item->value->name instanceof Identifier
                && $this->scope->subjectReflection->hasMethod($item->value->name->toString())
            ) {
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

                // Laravel's mergeData() unions the keys before a merge with the merged ones, so the earlier key stays.
                $mergeResult->dropKeysHeldBy($analysis);
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

            if (JsEmitter::isIndexSignatureKey($keyName)) {
                $analysis->addSignatureEntry($keyName, $result);

                continue;
            }

            // When a child key overrides a parent spread key, clear stale parent tracking
            $analysis->forgetChannels($keyName);

            $analysis->addProperty($keyName, $result);
        }

        return $analysis;
    }

    /**
     * The keys `only()` keeps on a receiver other than the subject's own model that holds exactly one concrete model.
     *
     * `except()` is left out: its pool would list the accessors and relations `Model::except()` never returns.
     */
    private function analyzeReceiverModelFilter(MethodCall $call): ?ResourceAnalysis
    {
        if ($this->filtersOwnModel($call)
            || ! $call->name instanceof Identifier
            || $call->name->toLowerString() !== 'only'
            || $call->isFirstClassCallable()
        ) {
            return null;
        }

        $receiver = resolve(ReceiverClassResolver::class)->resolve($call->var, $this->scope);
        $resolver = resolve(ReceiverMethodReturnResolver::class);
        $model = $receiver !== null && ! $receiver->shortCircuits ? $resolver->singleModel($receiver) : null;

        if ($model === null
            || new ReflectionClass($model)->isAbstract()
            || ! $resolver->typesAsModelFilter($model, 'only')
        ) {
            return null;
        }

        $filtering = $this->nestedAnalyzer($this->scope->subjectReflection, $this->methodName, $model);

        return $filtering->analyzeThisAttributeFilter(new MethodCall(new Variable('this'), $call->name, $call->args));
    }

    /**
     * Classify a top-level spread's value expression via InlineArrayHandler::classifySpreadArm()
     * and flatten its shape into this resource's own properties. Null for anything the classifier
     * declines, or takes only by its type, so the caller falls through to $this->merge()'s own handling.
     */
    private function analyzeSpreadArm(Expr $expr): ?MethodAnalysis
    {
        $arm = InlineArrayHandler::classifySpreadArm($expr, $this->scope, $this);

        if ($arm === null || isset($arm['type'])) {
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

        $analysis = resolve(AstEngine::class)->analyzeMethod($resourceFqcn, 'toArray');

        // resolve() runs the spread resource's own filter.
        self::reindexNumericKeyedArrays($analysis, new ReflectionClass($resourceFqcn));

        return $analysis;
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

            // Model::toArray() writes an Arrayable value as its toArray() array, which the filter enters.
            $keepsKeys = ($tsInfo['keepsKeys'] ?? false) && ! $resolver->serializesAsArray($modelFqcn, $column);

            $analysis->addProperty($column, ValueResult::withAttributeChannels(
                ['type' => $tsInfo['type'], 'optional' => $optional],
                [...$tsInfo, 'keepsKeys' => $keepsKeys],
            ));
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
        $merged = $this->mergedArrayLiteral($call, $this->scope->subjectReflection, $this->methodName);

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
        if ($this->isProxyFetch($expr, $this->scope)) {
            return $this->isResourceCollection($this->scope) ? null : $this->buildModelSerializedAnalysis();
        }

        return $this->analyzeArrayExpression($expr, topLevel: false);
    }

    /**
     * Resolve and analyze what the parent class's declaration of a method hands a `parent::` call to it.
     *
     * A whole read is the method's own return, which takes the collected list flat; a spread or variable builds on it.
     */
    protected function analyzeParentCall(string $method, bool $whole = false): ?ResourceAnalysis
    {
        $parentClass = $this->scope->subjectReflection->getParentClass();

        if ($parentClass === false) {
            return null;
        }

        if (in_array($method, self::FRAMEWORK_READS, true) && $this->runsFrameworkRead($parentClass, $method)) {
            return $this->frameworkMethodAnalysis($method, $whole);
        }

        if ($parentClass->getName() === JsonResource::class) {
            return $method === 'toArray' ? $this->buildModelSerializedAnalysis() : null;
        }

        // Read before a parent analyzer is built, which would answer the collected list's response instead.
        if (resolve(ResourceCollectionBody::class)->parentCallSendsCollectedList($method, $this->scope->subjectReflection)) {
            return $this->collectedListAnalysis();
        }

        return $this->nestedAnalysis($parentClass, $method, $whole);
    }

    /**
     * The method a `parent::` call names when the analyzer reads it: the analyzed method itself, or, in an API
     * resource, one of FRAMEWORK_READS.
     */
    protected function readParentCall(Expr $expr): ?string
    {
        if (! $expr instanceof StaticCall || ! $expr->name instanceof Identifier || ! $this->isParentCallTo($expr)) {
            return null;
        }

        $method = $expr->name->toString();
        $readsFramework = in_array($method, self::FRAMEWORK_READS, true)
            && $this->runtimeClass()->isSubclassOf(JsonResource::class);

        return $method === $this->methodName || $readsFramework ? $method : null;
    }

    /**
     * The method a `$this->` call names when the runtime class runs JsonResource's own body for it.
     */
    private function readThisFrameworkCall(Expr $expr): ?string
    {
        if (! $expr instanceof MethodCall || ! $expr->name instanceof Identifier || ! $this->hasThisReceiver($expr)) {
            return null;
        }

        $method = $expr->name->toString();

        return $this->thisRunsFrameworkBody($method) ? $method : null;
    }

    /**
     * Whether `$this->$method()` runs Laravel's own body for one of FRAMEWORK_READS; `$this` is the runtime class, so
     * its declaration decides.
     */
    private function thisRunsFrameworkBody(string $method): bool
    {
        return in_array($method, self::FRAMEWORK_READS, true) && $this->runsFrameworkRead($this->runtimeClass(), $method);
    }

    /**
     * Whether a class runs Laravel's own declaration of one of FRAMEWORK_READS: JsonResource's, or the resolve() a
     * JSON:API resource inherits from JsonApiResource.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function runsFrameworkRead(ReflectionClass $class, string $method): bool
    {
        return $this->declaredByJsonResource($class, $method)
            || ($method === 'resolve' && JsonApiResources::runsFrameworkResolve($class->getName()));
    }

    /**
     * What a JsonResource method returns for the runtime class: with() its `$with` default, resolve() its filtered
     * toArray() or a JSON:API resource object under `data`, and jsonSerialize() its resolve(); null where that is not
     * read statically or re-enters a read.
     */
    private function frameworkMethodAnalysis(string $method, bool $whole = false): ?ResourceAnalysis
    {
        $runtime = $this->runtimeClass();

        if ($method === 'with') {
            return $this->withDefaultAnalysis();
        }

        if (JsonApiResources::runsFrameworkResolve($runtime->getName())) {
            return $this->jsonApiDocumentDataAnalysis();
        }

        if ($method === 'jsonSerialize' && ! $this->declaredByJsonResource($runtime, 'resolve')) {
            return $this->runtimeClassAnalysis('resolve', $whole);
        }

        // From 12.45 resolve() reaches toArray() through toAttributes(), which an override or `$attributes` replaces.
        if ($this->replacesJsonResource($runtime, 'resolveResourceData')
            || $this->replacesJsonResource($runtime, 'toAttributes')
            || $runtime->hasProperty('attributes')
        ) {
            return null;
        }

        if ($this->reentersRuntimeMethod('toArray')) {
            return null;
        }

        // A collection's toArray() analysis is the response that wraps the list, while resolve() returns the list.
        if (resolve(ResourceCollectionBody::class)->parentCallSendsCollectedList($method, $this->scope->subjectReflection, $runtime)) {
            return $whole ? $this->flatCollectedListAnalysis() : $this->collectedListAnalysis();
        }

        $analysis = $this->runtimeClassAnalysis('toArray', $whole);

        if ($analysis !== null) {
            self::reindexNumericKeyedArrays($analysis, $runtime);
        }

        return $analysis;
    }

    /**
     * The analysis of one of the runtime class's own methods, null where a read of it encloses this one: Laravel
     * re-enters it until the stack runs out.
     */
    private function runtimeClassAnalysis(string $method, bool $whole): ?ResourceAnalysis
    {
        return $this->reentersRuntimeMethod($method) ? null : $this->nestedAnalysis($this->runtimeClass(), $method, $whole);
    }

    /**
     * Whether a read of the runtime class's method encloses this one.
     */
    private function reentersRuntimeMethod(string $method): bool
    {
        return $method === $this->methodName || isset($this->methodsOnStack[$method]);
    }

    /**
     * Analyze a method of the subject's parent or of the runtime class in an analyzer of its own, whose lenient reads
     * count as this one's; a whole read there stays whole only when this one's is.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function nestedAnalysis(ReflectionClass $class, string $method, bool $whole = false): ResourceAnalysis
    {
        $analyzer = $this->nestedAnalyzer($class, $method, $this->scope->modelClass);
        $analyzer->returnedWhole = $whole;

        $analysis = $analyzer->analyze();
        $this->lenientReads += $analyzer->lenientReads;

        return $analysis;
    }

    /**
     * The one place a nested analyzer is built: it reads a body as the runtime class runs it, with the methods whose
     * reads enclose it and the share() whose casts InertiaSharedDataAnalyzer applies.
     *
     * @param  ReflectionClass<object>  $class
     * @param  class-string<Model>|null  $modelClass
     */
    private function nestedAnalyzer(ReflectionClass $class, string $method, ?string $modelClass): self
    {
        $analyzer = new self(
            $class,
            $modelClass,
            $method,
            $this->handlerProfile,
            carriesImports: $this->scope->carriesImports,
            runtimeClass: $this->runtimeClass(),
        );
        $analyzer->sharedDataShareClass = $this->sharedDataShareClass();
        $analyzer->methodsOnStack = $this->methodsOnStack + [$this->methodName => true];

        return $analyzer;
    }

    /**
     * The keys of the runtime class's `$with` default, which JsonResource::with() returns; null when a method writes
     * the property, or its default is not an array with string keys.
     */
    private function withDefaultAnalysis(): ?ResourceAnalysis
    {
        $runtime = $this->runtimeClass();

        if (! resolve(SubjectPropertyTypeResolver::class)->holdsDefault($runtime, 'with')) {
            return null;
        }

        $default = $runtime->getProperty('with')->getDefaultValue();

        if (! is_array($default) || array_any(array_keys($default), fn (int|string $key): bool => is_int($key))) {
            return null;
        }

        $analysis = new ResourceAnalysis;
        $values = resolve(ValueResolver::class);

        foreach ($default as $key => $value) {
            $analysis->addProperty((string) $key, $values->resolveConstantValue($value) ?? ValueResult::unknown());
        }

        return $analysis;
    }

    /**
     * Whether a class runs JsonResource's own declaration of a method.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function declaredByJsonResource(ReflectionClass $class, string $method): bool
    {
        return $class->hasMethod($method) && $class->getMethod($method)->getDeclaringClass()->getName() === JsonResource::class;
    }

    /**
     * Whether a class runs its own declaration of a method rather than JsonResource's.
     *
     * @param  ReflectionClass<object>  $class
     */
    private function replacesJsonResource(ReflectionClass $class, string $method): bool
    {
        return $class->hasMethod($method) && ! $this->declaredByJsonResource($class, $method);
    }

    /**
     * The collected list as the value a spread, a variable, `+` or `array_merge()` builds on: its items under numeric
     * keys, which nothing re-indexes before the keys written beside them.
     */
    private function collectedListAnalysis(): ResourceAnalysis
    {
        $analysis = new ResourceAnalysis;
        $analysis->addProperty('[key: number]', ResourceCollections::elementValue($this->scope->collectedElement));

        return $analysis;
    }

    /**
     * Resolve and analyze a $this->method() spread; $topLevel carries the caller's own flatten-eligibility down into
     * the target's own return (see analyzeReturnArray()), and $call places the guard proofs that hold for it. The
     * caller's state it sets aside is restored in a `finally`.
     */
    protected function analyzeThisMethodSpread(string $methodName, bool $topLevel = true, ?Expr $call = null): ?ResourceAnalysis
    {
        if (! $this->scope->subjectReflection->hasMethod($methodName)) {
            return null; // @codeCoverageIgnore
        }

        if (isset($this->scope->visitedSpreadMethods[$methodName])) {
            return null;
        }

        if ($this->thisRunsFrameworkBody($methodName)) {
            return $this->frameworkMethodAnalysis($methodName);
        }

        $method = $this->scope->subjectReflection->getMethod($methodName);
        $context = resolve(MethodLocator::class)->locate($this->scope->subjectReflection->getName(), $methodName);
        $targetMethod = $context?->method;

        if ($targetMethod === null || $targetMethod->stmts === null) {
            return null;
        }

        // Read in the caller's tables, before they are set aside.
        $passed = $call instanceof CallLike ? new CallSite($call, $this->scope, $this)->passedTo($method) : [];
        $caller = $this->scope->enterSubjectMethod(
            $methodName,
            LaravelTsPublish::methodDeclaringFileClass($method),
            $this->proofsAcrossCall($this->scope, $call),
        );

        $readsAttributeNames = $this->readsKeylessAttributeNames;

        try {
            $this->seedVarBindings($method, $targetMethod->stmts, $passed);

            // Only the attributes Laravel builds from a JSON:API toAttributes() map a keyless name to the model's.
            $this->readsKeylessAttributeNames = $methodName === 'toAttributes' && JsonApiResources::is($this->runtimeClass()->getName());
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
            $this->readsKeylessAttributeNames = $readsAttributeNames;
            $this->scope->leaveSubjectMethod($methodName, $caller);
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
            $this->layTsCasts($attr->newInstance()->types, $analysis);
        }
    }

    /**
     * Lay one #[TsCasts] map over an analysis, updating every entry of a key it names and adding a key the analysis
     * lacks; with $keptCasts, a model's map retypes only the keys the analysis has and none of those it holds.
     *
     * @param  array<string, string|array{type: string, import?: string, optional?: bool}>  $types
     * @param  CastMap|null  $keptCasts  the casts a model's map never replaces
     */
    private function layTsCasts(array $types, ResourceAnalysis $analysis, ?array $keptCasts = null): void
    {
        $keys = array_column($analysis->properties, 'name');
        $types = JsEmitter::castsByKey($types, $keys);

        JsEmitter::warnAmbiguousCasts($this->subjectReflection()->getName(), array_keys($types), $keys);

        foreach ($types as $property => $value) {
            // PHP stores a numeric cast key as an int, while a property names its key as a string.
            $property = (string) $property;

            if ($keptCasts !== null && (isset($keptCasts[$property]) || ! in_array($property, $keys, true))) {
                continue;
            }

            $type = is_array($value) ? $value['type'] : $value;
            $optional = is_array($value) && isset($value['optional']) ? (bool) $value['optional'] : null;
            $import = is_array($value) ? ($value['import'] ?? null) : null;

            if ($optional === true) {
                ['type' => $type, 'optional' => $optional] = JsEmitter::signatureSafeMember($property, $type, true);
            }

            $found = false;

            // Every entry of a key written twice: the last one publishes, and the reconciler reads them all.
            foreach ($analysis->properties as &$prop) {
                if ($prop['name'] === $property) {
                    $prop['type'] = $type;
                    unset($prop['bodyType'], $prop['fillType']);

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

    /**
     * Build a ResourceAnalysis for a ResourceCollection subclass whose toArray() sends its collected list, all of it
     * the runtime class's: its collected element, its live wrap key and its keys.
     *
     * The wrap key names the one property, `{ data: R[] }` by default, keyed as `Record<string, R>` when the class
     * preserves keys; with no key that same list is the flatTypeAlias directly. Raw items without an element are an
     * `unknown` list.
     */
    protected function buildCollectionDelegatedAnalysis(): ResourceAnalysis
    {
        /** @var class-string<ResourceCollection> $runtimeName */
        $runtimeName = $this->runtimeClass()->getName();
        $wrapKey = ResourceWrapping::key($runtimeName);

        if ($wrapKey === null) {
            return $this->flatCollectedListAnalysis();
        }

        $list = $this->collectedListValue();
        $analysis = new ResourceAnalysis;

        if ($list !== null) {
            $analysis->addProperty($wrapKey, $list);
        }

        return $analysis;
    }

    /**
     * The collected list as a whole value, the bare list `resolve()` returns: the flatTypeAlias, on its element's
     * import channel; empty where Laravel throws.
     */
    private function flatCollectedListAnalysis(): ResourceAnalysis
    {
        $list = $this->collectedListValue();

        return $list === null
            ? new ResourceAnalysis
            : new ResourceAnalysis(flatTypeAlias: $list['type'], flatTypeAliasFqcn: $this->scope->collectedElement['fqcn'] ?? null);
    }

    /**
     * The runtime class's collected list as a value, or null where Laravel throws for a pick that is no API resource,
     * mapping the items into one the run does not publish.
     *
     * @return ValueExpressionResult|null
     */
    private function collectedListValue(): ?array
    {
        $element = $this->scope->collectedElement;
        $runtime = $this->runtimeClass();

        return $element === null && ! ResourceCollections::keepsRawItems($runtime)
            ? null
            : ResourceCollections::listValue($element, ResourceCollections::preservesKeys($runtime));
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
        /** @var array<string, ResourceAnalysis> $partlyRead */
        $partlyRead = [];

        foreach ($candidates as $return) {
            if ($return->expr instanceof Variable && is_string($return->expr->name)
                && ! array_key_exists($return->expr->name, $variables)) {
                $read = $this->readVariable($stmts, $return->expr->name);
                $variables[$return->expr->name] = $read !== null && $read[1] ? $read[0] : null;

                if ($read !== null && ! $read[1]) {
                    $partlyRead[$return->expr->name] = $read[0];
                }
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

        $merged = count($analyses) === 1 ? $analyses[0] : $this->mergeReturnBranches($analyses);

        // A variable skipped only for a lenient read still returns on its path: a key no read branch sets may be there.
        foreach ($partlyRead as $name => $branch) {
            if ($variables[$name] === null) {
                $this->addKeysOnlySkippedBranchSets($merged, $branch);
            }
        }

        return $merged;
    }

    /**
     * Add each key and signature entry only a skipped branch sets, optional; a key the read branches set keeps
     * their type and presence.
     */
    private function addKeysOnlySkippedBranchSets(ResourceAnalysis $merged, ResourceAnalysis $skipped): void
    {
        $skipped->dropKeysHeldBy($merged);

        foreach ($skipped->properties as $index => $property) {
            $skipped->properties[$index]['optional'] = ! JsEmitter::isIndexSignatureKey($property['name']);
        }

        $merged->merge($skipped);
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

        /** @var array<array-key, list<AnalyzedProperty>> PHP stores a numeric name as an int key */
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
            // A branch with no fill stands in with its own value. A fill equal to the merged type is kept too: that
            // type is a fill, and a reconcile that puts it back must find it again.
            $fillTypes = array_map(fn (array $e): string => $e['fillType'] ?? $e['bodyType'] ?? $e['type'], $entries);
            $fillType = array_column($entries, 'fillType') === []
                ? null
                : $this->branchUnion($fillTypes, $dropsUntypedBranches, $keepsLoneNull);

            $presentInAll = count($entries) === $branchCount;
            $keepsKeys = array_all($entries, fn (array $e): bool => $e['keepsKeys'] ?? false);
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
                'name' => (string) $name,
                'type' => $type,
                'optional' => $optional,
                'description' => $description,
                ...($bodyType === $type ? [] : ['bodyType' => $bodyType]),
                ...($fillType === null ? [] : ['fillType' => $fillType]),
                ...($keepsKeys ? ['keepsKeys' => true] : []),
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
     * publish that cast. It is optional where any branch's cast says so, since the union is; otherwise it keeps a flag
     * only where every branch's cast sets the same one.
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

            $anyOptional = array_any(
                $branchCasts,
                static fn (?array $branchCast): bool => ($branchCast['optional'] ?? null) === true,
            );

            $casts[(string) $name] = match (true) {
                $anyOptional => ['type' => $cast['type'], 'import' => $cast['import'], 'optional' => true],
                array_all($branchCasts, static fn (?array $branchCast): bool => $branchCast === $cast) => $cast,
                default => ['type' => $cast['type'], 'import' => $cast['import']],
            };
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

            if (! $this->isResourceFetch($instanceOf->expr)) {
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
