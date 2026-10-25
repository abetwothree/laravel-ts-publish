<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallSite;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\BuildsInlineObjectTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNullablePayloads;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesAuthHelperCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Members\MethodReturnTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Members\SubjectMethodTypeResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\AuthUserResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

/**
 * Resource construction via static calls: `EnumResource::make()`/`::collection()`, `SomeResource::make()`/
 * `::collection()`, a chain onto a resource-resolving receiver, and the `$this::`-style shapes. Its guard order is
 * load-bearing; it declines a `$this::` method nothing declares and a class expression it cannot name.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class StaticCallHandler implements ExpressionHandler
{
    use BuildsInlineObjectTypes;
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use ReadsNullablePayloads;
    use ResolvesAuthHelperCalls;
    use ResolvesEnumPropertyArgTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, MethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // SomeResource::collection(...)->resolve() — strip the trailing ->resolve() and delegate. resolve() runs the
        // resource's own toArray(), so a null payload never makes it null.
        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'resolve'
            && $expr->var instanceof StaticCall
        ) {
            $resolved = $this->analyzeStaticCall($expr->var, $scope, $engine);

            return isset($resolved['resourceFqcn']) ? [...$resolved, 'type' => ValueResult::stripNullArm($resolved['type'])] : $resolved;
        }

        // new SomeResource(...)->resolve() — resolve() is Laravel's serializer, not the resource's own
        // method, so strip it and keep the constructed receiver's type. Must precede the fluent arm
        // below, which would otherwise see this shape first and decline a foreign resource's resolve().
        if ($expr instanceof MethodCall
            && $expr->name instanceof Identifier
            && $expr->name->toString() === 'resolve'
            && $expr->var instanceof New_
        ) {
            $receiver = $engine->resolve($expr->var);

            if (isset($receiver['resourceFqcn'])) {
                return $this->resolvedBody($expr->var, $receiver, $receiver['resourceFqcn'], $scope);
            }
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
            $site = new CallSite($expr, $scope, $engine);

            return resolve(SubjectMethodTypeResolver::class)
                ->resolve($scope, $expr->name->toString(), $site, $scope->staticCallClass($expr));
        }

        // `$this->resource::staticMethod()`. Any other `$this->prop::m()` is ReceiverMethodCallHandler's.
        if ($expr instanceof StaticCall
            && $expr->class instanceof Expr
            && $this->isProxyFetch($expr->class, $scope)
            && $expr->name instanceof Identifier
        ) {
            return $this->analyzeStaticMethodOnResource($expr, $expr->name->toString(), $scope, $engine);
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

        // A value read of `parent::toArray()` over a parent that sends the collected list is that list.
        if ($expr instanceof StaticCall && $this->readsParentCollectedList($expr, $scope)) {
            return ResourceCollections::listValue($scope->collectedElement, ! $scope->reindexesNumericKeys);
        }

        // EnumResource::make($this->prop) or SomeResource::make/collection(); a `$expr::m()` it cannot name declines.
        if ($expr instanceof StaticCall) {
            return $this->resolveStaticCallClassName($expr) === null ? null : $this->analyzeStaticCall($expr, $scope, $engine);
        }

        return null;
    }

    /**
     * Whether a call is `parent::toArray()` over a parent whose toArray() sends the collected list; a value read of any
     * other `parent::` call is not read as that list.
     */
    private function readsParentCollectedList(StaticCall $call, AnalysisScope $scope): bool
    {
        return $this->isParentCallTo($call, 'toArray')
            && resolve(ResourceCollectionBody::class)->parentCallSendsCollectedList('toArray', $scope->subjectReflection);
    }

    /**
     * Analyze a static method call like EnumResource::make() or SomeResource::make/collection().
     *
     * @return ValueExpressionResult|null null where the subject's `__call()` answers the call and types nothing, so
     *                                    ReceiverMethodCallHandler reads it as it reads `$this->m()`
     */
    private function analyzeStaticCall(StaticCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = ValueResult::unknown();
        $className = $this->resolveStaticCallClassName($call);
        $methodName = $call->name instanceof Identifier ? $call->name->toString() : null;

        if ($className === null || $methodName === null) {
            return $result; // @codeCoverageIgnore
        }

        // `self`/`static` read as the class they name in the body, so those calls are treated as ClassName::*() calls.
        if ($call->class instanceof Name) {
            $className = $this->scopeClassName($call->class, $scope);
        }

        // In an instance method PHP sends a static-syntax call to a method the subject lacks to `__call()`.
        if (resolve(ReceiverClassResolver::class)->sendsToSubjectCall($call, $scope)) {
            return resolve(SubjectMethodTypeResolver::class)
                ->resolve($scope, $methodName, new CallSite($call, $scope, $engine), $scope->staticCallClass($call));
        }

        // EnumResource::make($this->prop)
        if ($this->isEnumResourceClass($className) && $methodName === 'make') {
            return $this->analyzeEnumResourceMake($call, $className, $scope, $engine);
        }

        // EnumResource::collection($this->prop) — must precede the generic isResourceClass()
        // checks below: EnumResource extends JsonResource, so those would match it too and
        // yield the unsuffixed 'EnumResource[]' instead of resolving the wrapped enum.
        if ($this->isEnumResourceClass($className) && $methodName === 'collection') {
            return $this->analyzeEnumResourceCollection($call, $className, $scope, $engine);
        }

        // SomeCollection::make() builds the collection a key nests, so it must precede the generic make() arm below;
        // SomeCollection::collection() is JsonResource::collection(), a list of collections that arm spells.
        if (is_a($className, ResourceCollection::class, true) && $methodName === 'make') {
            $nested = resolve(ResourceCollectionBody::class)->nestedValue($className);

            if ($nested !== null) {
                return [...$nested, 'optional' => $this->hasConditionalArgument($call, $className)];
            }
        }

        // SomeResource::make($this->prop) — nested resource
        if ($this->isResourceClass($className) && $methodName === 'make') {
            /** @var class-string $className */
            $type = $this->resourceWrapType($call, $className, $scope, $engine);
            $optional = $this->hasConditionalArgument($call, $className);

            return [
                ...$result,
                'type' => $type,
                'optional' => $optional,
                ...($type === 'null' ? [] : ['resourceFqcn' => $className]),
            ];
        }

        // SomeResource::collection(...) — array or keyed record of nested resource
        if ($this->isResourceClass($className) && $methodName === 'collection') {
            $resourceName = TsNaming::resourceTypeName($className);
            $optional = $this->hasConditionalArgument($call, $className);

            /** @var class-string $className */
            return [
                ...$result,
                'type' => ResourceCollections::listType($resourceName, new ReflectionClass($className)),
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // Any other existing class — its declared return type, else the shape its literal body spells.
        // Accepted only when it cannot break generated imports; see MethodReturnTypeResolver::resolve().
        if (class_exists($className)) {
            $site = new CallSite($call, $scope, $engine);

            return resolve(MethodReturnTypeResolver::class)->resolve($className, $methodName, $site) ?? $result;
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
        if ($resourceFqcn === null || ! $this->holdsOneResource($expr->var, $receiverResult['type'], $resourceFqcn, $scope)) {
            return null;
        }

        $methodName = $expr->name->toString();

        if (! method_exists($resourceFqcn, $methodName)) {
            return null;
        }

        $method = new ReflectionMethod($resourceFqcn, $methodName);

        // Not self-returning: the expression is the method's payload, not the resource. Resolving it
        // needs the receiver's own analyzer, so only the subject or the runtime class is in scope — a foreign
        // resource class returns null and keeps the `unknown` floor rather than claiming its keys.
        if (! $this->methodPreservesReceiverType($method, $resourceFqcn)) {
            $built = match ($resourceFqcn) {
                $scope->subjectReflection->getName() => $scope->subjectReflection,
                $scope->runtimeReflection->getName() => $scope->runtimeReflection,
                default => null,
            };

            if ($built === null) {
                return null;
            }

            $analysis = $engine->spreadAnalysis($methodName, $built);

            if ($analysis === null || $analysis->properties === []) {
                return null;
            }

            return ['type' => $this->buildInlineObjectType($analysis), 'optional' => false];
        }

        if ($this->methodReturnAllowsNull($method)) {
            $receiverResult['type'] = ValueResult::withNullArm($receiverResult['type']);
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

        // extractReturnTypeFromDocblock() trims the type and collapses its whitespace, so no trim() before comparing.
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
     * What `new X(...)->resolve()` returns: the value X nests as in a resource key, never the response an Inertia prop
     * types for its receiver, so a collection's own type or list and a single resource's own type.
     *
     * @param  ValueExpressionResult  $receiver
     * @param  class-string  $resourceFqcn  the receiver's resource channel
     * @return ValueExpressionResult
     */
    private function resolvedBody(New_ $new, array $receiver, string $resourceFqcn, AnalysisScope $scope): array
    {
        $class = $new->class instanceof Name ? $this->scopeClassName($new->class, $scope) : null;
        $nested = $class !== null && is_a($class, ResourceCollection::class, true)
            ? resolve(ResourceCollectionBody::class)->nestedValue($class)
            : null;

        return $nested !== null
            ? [...$nested, 'optional' => $receiver['optional']]
            : [...$receiver, 'type' => JsonApiResources::instanceType($resourceFqcn)];
    }

    /**
     * Whether a receiver holds one instance of the resource its result names: typed as that resource, or built as it
     * by `new`, `make()` or `toResource()` through any chain, which an Inertia prop types as the response it sends.
     *
     * @param  class-string  $resourceFqcn
     */
    private function holdsOneResource(Expr $receiver, string $type, string $resourceFqcn, AnalysisScope $scope): bool
    {
        $held = ValueResult::stripNullArm($type);

        if ($held === TsNaming::resourceTypeName($resourceFqcn) || $held === JsonApiResources::instanceType($resourceFqcn)) {
            return true;
        }

        return match (true) {
            $receiver instanceof New_ => $receiver->class instanceof Name
                && $this->scopeClassName($receiver->class, $scope) === $resourceFqcn,
            $receiver instanceof StaticCall => $receiver->class instanceof Name
                && $receiver->name instanceof Identifier
                && $receiver->name->toString() === 'make'
                && $this->scopeClassName($receiver->class, $scope) === $resourceFqcn,
            $receiver instanceof MethodCall => ($receiver->name instanceof Identifier && $receiver->name->toString() === 'toResource')
                || $this->holdsOneResource($receiver->var, $type, $resourceFqcn, $scope),
            default => false,
        };
    }

    /**
     * Analyze EnumResource::make($this->prop) — resolve the enum class from the model property, else from the payload.
     *
     * @return ValueExpressionResult
     */
    private function analyzeEnumResourceMake(StaticCall $call, string $className, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $payload = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        if ($payload === null) {
            return ValueResult::unknown();
        }

        return $this->enumWrapValue($payload, $scope, $engine) ?? ValueResult::unknown();
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
    private function analyzeEnumResourceCollection(StaticCall $call, string $className, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        $payload = $this->resourcePayloadArguments($call, $className)->at(0)?->value;

        return $payload === null
            ? ValueResult::unknown()
            : $this->enumCollectionValue($payload, $scope, $engine) ?? ValueResult::unknown();
    }

    /**
     * Analyze a `$this->resource::staticMethod()` call against the classes `$this->resource` holds that declare it.
     *
     * @return ValueExpressionResult
     */
    private function analyzeStaticMethodOnResource(StaticCall $call, string $methodName, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        return resolve(SubjectMethodTypeResolver::class)
            ->resolveForwarded($scope, $methodName, new CallSite($call, $scope, $engine)) ?? ValueResult::unknown();
    }
}
