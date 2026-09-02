<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsResourceSubject;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesRelatedModelTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReflectedTypeAcceptor;
use AbeTwoThree\LaravelTsPublish\Ast\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Resource construction via static calls: `EnumResource::make()`/`::collection()`,
 * `SomeResource::make()`/`::collection()`, a fluent chain onto a resource-resolving receiver, and
 * the `$this::`/`$this->resource::`/`$this->relation::` static-call shapes.
 *
 * The internal guard order below reproduces the pre-extraction chain exactly and is load-bearing —
 * several guards must precede others, as each inline comment explains.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 */
final class StaticCallHandler implements ExpressionHandler
{
    use BuildsInlineObjectTypes;
    use ChecksPreserveKeys;
    use InspectsAstNodes;
    use InspectsResourceSubject;
    use ResolvesAuthHelperCalls;
    use ResolvesEnumPropertyArgTypes;
    use ResolvesRelatedModelTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // `$variable::staticMethod()` in a whenLoaded closure. Must precede the general StaticCall
        // handler, which only matches class-name receivers.
        if ($scope->closureRelationModelClass !== null
            && $expr instanceof StaticCall
            && $expr->class instanceof Variable
            && is_string($expr->class->name)
            && $expr->class->name !== 'this'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeRelatedModelMethodCall($expr->name->toString(), $scope);
        }

        // SomeResource::collection(...)->resolve() — strip the trailing ->resolve() and delegate.
        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'resolve'
            && $expr->var instanceof StaticCall
        ) {
            return $this->analyzeStaticCall($expr->var, $scope, $engine);
        }

        // A fluent method chained onto a resource-resolving receiver — `new self($x)->foo()`,
        // `SomeResource::make($x)->foo()`, or a chain of such calls — keeps the receiver's type
        // when the method's own declared return type hands the same instance back.
        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && ($expr->var instanceof New_ || $expr->var instanceof StaticCall || $expr->var instanceof MethodCall)
        ) {
            $selfReturning = $this->analyzeSelfReturningResourceMethodCall($expr, $scope, $engine);

            if ($selfReturning !== null) {
                return $selfReturning;
            }
        }

        // `$this::staticMethod()` — the resource itself is the receiver.
        if ($expr instanceof StaticCall
            && $expr->class instanceof Variable
            && $expr->class->name === 'this'
            && $expr->name instanceof Identifier
        ) {
            return resolve(SubjectMethodTypeResolver::class)->resolve($scope, $expr->name->toString());
        }

        // `$this->resource::staticMethod()`. Must precede the closure-context PropertyFetch handler below.
        if ($expr instanceof StaticCall
            && $expr->class instanceof PropertyFetch
            && $expr->class->var instanceof Variable
            && $expr->class->var->name === 'this'
            && $expr->class->name instanceof Identifier
            && $expr->class->name->toString() === 'resource'
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeStaticMethodOnResource($expr->name->toString(), $scope);
        }

        // `$this->relation::staticMethod()` inside a whenLoaded closure — use the related model.
        if ($expr instanceof StaticCall
            && $expr->class instanceof PropertyFetch
            && $expr->name instanceof Identifier
        ) {
            /** @var class-string<Model>|null $closureModelClass */
            $closureModelClass = $scope->closureRelationModelClass;

            if ($closureModelClass !== null) {
                return $this->analyzeRelatedModelMethodCall($expr->name->toString(), $scope);
            }
        }

        // `Auth::user()`/`Auth::id()`. Must precede the general StaticCall arm below, whose reflection
        // fallback sees a facade with no such real method and floors the value at unknown.
        if ($expr instanceof StaticCall
            && $expr->class instanceof Name
            && $expr->class->getLast() === 'Auth'
            && $expr->name instanceof Identifier
            && ! $expr->isFirstClassCallable()
        ) {
            $auth = $this->authMethodResult($expr->name->toString(), resolve(AuthUserResolver::class)->model());

            if ($auth !== null) {
                return $auth;
            }
        }

        // EnumResource::make($this->prop) or SomeResource::make/collection()
        if ($expr instanceof StaticCall) {
            return $this->analyzeStaticCall($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Analyze a static method call like EnumResource::make() or SomeResource::make/collection().
     *
     * @return ValueExpressionResult
     */
    private function analyzeStaticCall(StaticCall $call, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $result = ValueResult::unknown();
        $className = $this->resolveStaticCallClassName($call);
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($className === null || $methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        // Resolve `self`/`static` so those calls are treated identically to ClassName::*() calls.
        if ($className === 'self' || $className === 'static') {
            $className = $scope->subjectReflection->getName();
        }

        // EnumResource::make($this->prop)
        if ($this->isEnumResourceClass($className) && $methodName === 'make') {
            return $this->analyzeEnumResourceMake($call, $className, $scope);
        }

        // EnumResource::collection($this->prop) — must precede the generic isResourceClass()
        // checks below: EnumResource extends JsonResource, so those would match it too and
        // yield the unsuffixed 'EnumResource[]' instead of resolving the wrapped enum.
        if ($this->isEnumResourceClass($className) && $methodName === 'collection') {
            return $this->analyzeEnumResourceCollection($call, $className, $scope);
        }

        // SomeCollection::make()/::collection() on a ResourceCollection subclass. Must precede the generic
        // checks below: ResourceCollection extends JsonResource, so isResourceClass() matches it too and
        // would yield the unsuffixed collection name instead of 'OrderItemResource[]'.
        if (is_a($className, ResourceCollection::class, true) && in_array($methodName, ['make', 'collection'], true)) {
            $collected = $this->resolveCollectedResourceClass($className);

            if ($collected !== null) {
                return [
                    ...$result,
                    'type' => $this->wrapCollectionElementType(LaravelTsPublish::resourceTypeName($collected), new ReflectionClass($className)),
                    'optional' => $this->hasConditionalArgument($call, $className),
                    'resourceFqcn' => $collected,
                ];
            }
        }

        // SomeResource::make($this->prop) — nested resource
        if ($this->isResourceClass($className) && $methodName === 'make') {
            $resourceName = LaravelTsPublish::resourceTypeName($className);
            $optional = $this->hasConditionalArgument($call, $className);

            /** @var class-string $className */
            return [
                ...$result,
                'type' => $resourceName,
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // SomeResource::collection(...) — array or keyed record of nested resource
        if ($this->isResourceClass($className) && $methodName === 'collection') {
            $resourceName = LaravelTsPublish::resourceTypeName($className);
            $optional = $this->hasConditionalArgument($call, $className);

            /** @var class-string $className */
            return [
                ...$result,
                'type' => $this->wrapCollectionElementType($resourceName, new ReflectionClass($className)),
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // Any other existing class — reflect the static method's return type. Accepted only when it
        // cannot break generated imports; see ReflectedTypeAcceptor::accept().
        if (class_exists($className)) {
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($className), $methodName);

            return resolve(ReflectedTypeAcceptor::class)->accept($tsInfo) ?? $result;
        }

        return $result;
    }

    /**
     * Resolve a fluent method chained onto a receiver that itself resolves to a resource — e.g.
     * `new self($x)->foo()`, `SomeResource::make($x)->foo()`, or a chain of such calls. The
     * receiver's own resolved result is returned unchanged when the method preserves it; otherwise
     * the method's own body is resolved, and an unreflectable receiver yields null to degrade.
     *
     * @return ValueExpressionResult|null
     */
    private function analyzeSelfReturningResourceMethodCall(MethodCall $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr->name instanceof Identifier) {
            return null; // @codeCoverageIgnore
        }

        $receiverResult = $engine->resolve($expr->var);
        $resourceFqcn = $receiverResult['resourceFqcn'] ?? null;

        // A collection receiver (e.g. ::collection()) resolves to an AnonymousResourceCollection
        // instance, not a $resourceFqcn instance — reflecting the method below would validate
        // against the wrong receiver, so exclude it rather than misfire on e.g. ->additional().
        if ($resourceFqcn === null || $receiverResult['type'] !== LaravelTsPublish::resourceTypeName($resourceFqcn)) {
            return null;
        }

        $methodName = $expr->name->toString();

        if (! method_exists($resourceFqcn, $methodName)) {
            return null;
        }

        $method = new ReflectionMethod($resourceFqcn, $methodName);

        // Not self-returning: the expression is the method's payload, not the resource. Resolving it
        // needs the receiver's own analyzer, so only the analyzer's own class is in scope — a foreign
        // resource class returns null and keeps the `unknown` floor rather than claiming its keys.
        if (! $this->methodPreservesReceiverType($method, $resourceFqcn)) {
            if ($resourceFqcn !== $scope->subjectReflection->getName()) {
                return null;
            }

            $analysis = $engine->spreadAnalysis($methodName);

            if ($analysis === null || $analysis->properties === []) {
                return null;
            }

            return ['type' => $this->buildInlineObjectType($analysis), 'optional' => false];
        }

        if ($this->methodReturnAllowsNull($method) && ! str_contains($receiverResult['type'], 'null')) {
            $receiverResult['type'] .= ' | null';
        }

        return $receiverResult;
    }

    /**
     * Whether a method's declared return type says it hands the same instance back — a native
     * `static`, `self`, or the resource class itself; falling back to a `@return $this` docblock
     * only when no native return type is declared at all. A union or intersection return type is
     * rejected outright and never falls through to the docblock.
     */
    private function methodPreservesReceiverType(ReflectionMethod $method, string $resourceFqcn): bool
    {
        $returnType = $method->getReturnType();

        if ($returnType instanceof ReflectionNamedType) {
            $name = $returnType->getName();

            return $name === 'static' || $name === 'self' || $name === $resourceFqcn;
        }

        if ($returnType !== null) {
            return false;
        }

        $docComment = $method->getDocComment();

        if ($docComment === false) {
            return false;
        }

        // extractReturnTypeFromDocblock()'s final fallback is `\S+`, so the token it returns
        // can never carry surrounding whitespace — no trim() needed before comparing.
        return LaravelTsPublish::extractReturnTypeFromDocblock($docComment) === '$this';
    }

    /**
     * Whether a self-returning method's native return type also allows null (`?static`). The
     * docblock-only `@return $this` fallback carries no nullability signal, so this only
     * inspects a `ReflectionNamedType` — the same shape methodPreservesReceiverType() required
     * to have already matched before this is ever called.
     */
    private function methodReturnAllowsNull(ReflectionMethod $method): bool
    {
        $returnType = $method->getReturnType();

        return $returnType instanceof ReflectionNamedType && $returnType->allowsNull();
    }

    /**
     * Analyze EnumResource::make($this->prop) — resolve the enum class from the model property.
     *
     * @return ValueExpressionResult
     */
    private function analyzeEnumResourceMake(StaticCall $call, string $className, AnalysisScope $scope): array
    {
        $payload = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        if ($payload === null) {
            return ValueResult::unknown();
        }

        return $this->resolveEnumFromPropertyArg($payload, $scope) ?? ValueResult::unknown();
    }

    /**
     * Analyze EnumResource::collection($this->prop) — resolve the enum class and array-wrap it.
     *
     * A first-class callable carries no argument at the call site to resolve the enum from — the
     * value is supplied later by whichever conditional method invokes it — so it degrades to
     * unknown rather than guessing, matching analyzeEnumResourceMake()'s FCC bail-out.
     *
     * @return ValueExpressionResult
     */
    private function analyzeEnumResourceCollection(StaticCall $call, string $className, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $payload = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        if ($payload === null) {
            return $result;
        }

        $enumResult = $this->resolveEnumFromPropertyArg($payload, $scope);

        if ($enumResult === null) {
            return $result;
        }

        // The resolved property may already be a collection type (an AsEnumCollection cast or a
        // list<Enum> accessor both resolve their own '[]' already) — only wrap when it isn't.
        $type = $enumResult['type'];
        $alreadyCollection = str_ends_with(rtrim(str_replace('| null', '', $type)), '[]');

        return [
            ...$enumResult,
            'type' => $alreadyCollection ? $type : ValueResult::arrayWrapType($type),
        ];
    }

    /**
     * Analyze a `$this->resource::staticMethod()` call against the wrapped class, then the @mixin model.
     *
     * Each reflection is accepted only when its tokens can be imported; see ReflectedTypeAcceptor::accept().
     *
     * @return ValueExpressionResult
     */
    private function analyzeStaticMethodOnResource(string $methodName, AnalysisScope $scope): array
    {
        $result = ValueResult::unknown();
        $wrappedClass = $this->resolveWrappedClass($scope);

        if ($wrappedClass !== null && method_exists($wrappedClass, $methodName)) {
            /** @var class-string $wrappedClass */
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($wrappedClass), $methodName);
            $accepted = resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        if ($scope->modelClass !== null && method_exists($scope->modelClass, $methodName)) {
            /** @var class-string $modelClass */
            $modelClass = $scope->modelClass;
            $tsInfo = LaravelTsPublish::methodOrDocblockReturnTypes(new ReflectionClass($modelClass), $methodName);
            $accepted = resolve(ReflectedTypeAcceptor::class)->accept($tsInfo);

            if ($accepted !== null) {
                return $accepted;
            }
        }

        return $result;
    }
}
