<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\Analysis\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\AstEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Calls\CallArguments;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\GuessesModelResources;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ReadsNullablePayloads;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\PaginatorSteps;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverType;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\EmptyReceiverGuess;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceResponseType;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResponseExtras;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionMethod;

/**
 * A resource a controller hands to `Inertia::render()` or a middleware shares, at any depth of the props, is sent as
 * its `toResponse()` data, not the value a nested resource key flattens it to: its body inside the live wrap key, a
 * collection's pagination members for each paginator its payload may hold, and the `with()` and `additional()` keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type GuessedCollection from GuessesModelResources
 * @phpstan-import-type PayloadPaginators from ResourceResponseType
 *
 * @internal
 */
final class InertiaResourcePropHandler implements ExpressionHandler
{
    use GuessesModelResources;
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use ReadsNullablePayloads;
    use ResolvesEnumPropertyArgTypes;
    use ResolvesModelRelationTypes;

    /**
     * Builds each response from the spelling this handler reads.
     */
    public function __construct(private readonly ResourceResponseType $responses = new ResourceResponseType) {}

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, New_::class, MethodCall::class, NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $result = $this->resolveBuilt($expr, $scope, $engine);

        return $result === null ? null : ValueResult::closeToDottedKeys($result);
    }

    /**
     * A resource or collection an expression builds, as sent with the keys an `additional()` call on it adds.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveBuilt(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine, ?ResponseExtras $added = null): ?array
    {
        return match (true) {
            $expr instanceof New_ => $this->resolveNew($expr, $scope, $engine, $added),
            $expr instanceof StaticCall => $this->resolveStatic($expr, $scope, $engine, $added),
            $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall => $this->resolveMethodCall($expr, $scope, $engine, $added),
            default => null,
        };
    }

    /**
     * `new SomeResource($model)` or `new SomeCollection($paginatorOrCollection)`.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveNew(New_ $expr, AnalysisScope $scope, ExpressionEngine $engine, ?ResponseExtras $added): ?array
    {
        if (! $expr->class instanceof Name) {
            return null;
        }

        $className = $this->scopeClassName($expr->class, $scope);

        if (is_a($className, ResourceCollection::class, true)) {
            return $this->namedCollection($expr, $className, $scope, $engine, $added);
        }

        return $this->sendsResourceObject($className) ? $this->singleResource($expr, $className, $scope, $engine, $added) : null;
    }

    /**
     * `SomeResource::make($model)`, `SomeCollection::make($items)`, `SomeResource::collection($items)` and
     * `EnumResource::collection($cases)`, the class named or as `self` or `static`.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveStatic(StaticCall $expr, AnalysisScope $scope, ExpressionEngine $engine, ?ResponseExtras $added): ?array
    {
        $className = $expr->class instanceof Name ? $this->scopeClassName($expr->class, $scope) : null;

        if ($className === null || ! $expr->name instanceof Identifier || $expr->isFirstClassCallable()) {
            return null;
        }

        if ($expr->name->toString() === 'collection') {
            return $this->resolveStaticCollection($expr, $className, $scope, $engine, $added);
        }

        if ($expr->name->toString() !== 'make') {
            return null;
        }

        // make() is `new static(...$parameters)`, so it is sent exactly as the `new` it spells.
        if (is_a($className, ResourceCollection::class, true)) {
            return $this->namedCollection($expr, $className, $scope, $engine, $added);
        }

        return $this->sendsResourceObject($className) ? $this->singleResource($expr, $className, $scope, $engine, $added) : null;
    }

    /**
     * `toResource()`, `toResourceCollection()`, with or without a class, and `additional()`, through `->` or `?->`.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveMethodCall(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ExpressionEngine $engine, ?ResponseExtras $added): ?array
    {
        if (! $call->name instanceof Identifier) {
            return null;
        }

        $result = match ($call->name->toString()) {
            'toResource' => $this->resolveToResource($call, $scope, $added),
            'toResourceCollection' => $this->resolveToResourceCollection($call, $scope, $engine, $added),
            'additional' => $this->resolveAdditional($call, $scope, $engine),
            default => null,
        };

        // A `?->` here or earlier in the chain ends the whole expression as null, where a plain `->` on null throws.
        return $result !== null && $this->chainShortCircuits($call)
            ? [...$result, 'type' => ValueResult::withNullArm($result['type'])]
            : $result;
    }

    /**
     * `->additional([...])` on a resource or collection this handler builds: its response with those keys, as encoded.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveAdditional(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $data = CallArguments::for($call, new ReflectionMethod(JsonResource::class, 'additional'))->named('data')?->value;
        $built = $call->var;

        // Each call replaces the keys an earlier one set.
        while (($built instanceof MethodCall || $built instanceof NullsafeMethodCall)
            && $built->name instanceof Identifier
            && $built->name->toString() === 'additional'
        ) {
            $built = $built->var;
        }

        if ($data === null) {
            return null;
        }

        return $this->resolveBuilt($built, $scope, $engine, ResponseExtras::ofValue(resolve(AstEngine::class)->analyzeEncodedValue($data, $engine)));
    }

    /**
     * `$model->toResource()` or `$model->toResource(SomeResource::class)`: each resource it may build, as sent.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveToResource(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ?ResponseExtras $added): ?array
    {
        $value = $this->analyzeToResourceCall($call, $scope);
        $responses = [];

        foreach (isset($value['resourceFqcn']) ? [$value['resourceFqcn']] : ($value['embeddedResourceFqcns'] ?? []) as $resource) {
            $response = $this->sendsResourceObject($resource) ? $this->responses->single($resource, $added) : null;

            if ($response === null) {
                return null;
            }

            $responses[] = $response;
        }

        return match (count($responses)) {
            0 => null,
            1 => [...$value, ...$responses[0], 'optional' => $value['optional']],
            default => [...$value, ...ValueResult::unionResults($responses), 'optional' => $value['optional']],
        };
    }

    /**
     * `$items->toResourceCollection()`, the collection Laravel guesses from the items' model, or
     * `->toResourceCollection(SomeResource::class)`, that resource's anonymous collection; each over the paginator the
     * receiver may be. A guessed named collection also takes what an empty receiver sends, unless its own admits it.
     *
     * @return ValueExpressionResult|null null for a receiver whose model, or a class, it cannot read
     */
    private function resolveToResourceCollection(
        MethodCall|NullsafeMethodCall $call,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?ResponseExtras $added,
    ): ?array {
        $args = $this->resourceCallArguments($call, Collection::class, 'toResourceCollection');
        $explicitArg = $args->named('resourceClass') ?? $args->at(0);

        if ($explicitArg !== null) {
            $explicit = resolve(ValueResolver::class)->resolveClassConstArgument($explicitArg->value);

            // Laravel calls `$explicit::collection($items)`; the receiver is read only for a class this sends.
            return match (true) {
                $explicit === null, $added !== null && $this->isEnumResourceClass($explicit) => null,
                $this->isEnumResourceClass($explicit) => $this->enumListResponse($call->var, $scope, $engine)
                    ?? $this->unnamedEnumListResponse(),
                $this->collectsAnonymously($explicit) => $this->responses->anonymousCollection(
                    $explicit,
                    $this->payloadPaginators($call->var, $scope, $engine),
                    $added,
                ),
                default => null,
            };
        }

        $receiver = $engine->resolve($call->var);
        $paginators = $this->payloadPaginators($call->var, $scope, $engine);
        $guessed = $this->guessedCollection($call->var, $receiver, $scope);

        if ($guessed === null) {
            return null;
        }

        if ($guessed['collection'] !== null) {
            $named = $this->responses->namedCollection($guessed['collection'], $paginators, $added);

            return $named === null || resolve(EmptyReceiverGuess::class)->responseAdmitsEmpty($guessed['collection'])
                ? $named
                : $this->responses->besideEmptyReceiver($named, $paginators, $added);
        }

        return $this->collectsAnonymously($guessed['resource'])
            ? $this->responses->anonymousCollection($guessed['resource'], $paginators, $added)
            : null;
    }

    /**
     * The collection Laravel's guess builds for the items of a `toResourceCollection()` receiver, from their model: the
     * one the receiver's type lists or paginates, else the element of an Eloquent collection the resolver names.
     *
     * @param  ValueExpressionResult  $receiver  the receiver as the engine resolves it
     * @return GuessedCollection|null
     */
    private function guessedCollection(Expr $var, array $receiver, AnalysisScope $scope): ?array
    {
        $embedded = $receiver['embeddedModelFqcns'] ?? [];
        $model = $receiver['modelFqcn'] ?? (count($embedded) === 1 ? $embedded[0] : null);

        if ($model === null || ! $this->listsItemsOf($receiver['type'], class_basename($model))) {
            $model = resolve(ReceiverClassResolver::class)->resolve($var, $scope)?->elementModel;
        }

        return $model !== null && is_a($model, Model::class, true) ? $this->resolveResourceCollectionForModel($model) : null;
    }

    /**
     * Whether each arm of a type is a list or a paginator of one model, a null arm aside, as `->` on null throws.
     */
    private function listsItemsOf(string $type, string $model): bool
    {
        return array_all(
            TsTypeString::splitTopLevelUnion(ValueResult::stripNullArm($type)),
            fn (string $arm): bool => $arm === $model.'[]' || $arm === $this->paginatorName($arm).'<'.$model.'>',
        );
    }

    /**
     * `SomeResource::collection($paginatorOrCollection)` — an anonymous collection of that resource.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveStaticCollection(
        StaticCall $expr,
        string $className,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?ResponseExtras $added,
    ): ?array {
        if ($this->isEnumResourceClass($className)) {
            return $added === null ? $this->enumResourceCollection($expr, $className, $scope, $engine) : null;
        }

        $result = $this->collectsAnonymously($className) ? $this->responses->anonymousCollection(
            $className,
            $this->payloadPaginators($this->resourcePayloadArguments($expr, $className)->at(0)?->value, $scope, $engine),
            $added,
        ) : null;

        return $result === null ? null : [...$result, 'optional' => $this->hasConditionalArgument($expr, $className)];
    }

    /**
     * `EnumResource::collection($cases)`: the enum list inside the anonymous collection's live wrap key.
     *
     * @return ValueExpressionResult|null null when no single enum resolves, as the nested value would not either
     */
    private function enumResourceCollection(StaticCall $expr, string $className, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $payload = $this->resourcePayloadArguments($expr, $className)->at(0)?->value;

        return $payload === null ? null : $this->enumListResponse($payload, $scope, $engine);
    }

    /**
     * What `EnumResource::collection($payload)` sends: the enum list inside the anonymous collection's wrap key.
     *
     * @return ValueExpressionResult|null null when no single enum resolves, as the nested value would not either
     */
    private function enumListResponse(Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        // A resource collects `collect($items)` as it collects `$items`.
        $list = $this->enumCollectionValue(CallArguments::collectArgument($payload) ?? $payload, $scope, $engine);

        return $list === null ? null : $this->responses->enumList($list);
    }

    /**
     * What an EnumResource collection sends over items naming no enum, whose nested value names an unpublished type.
     *
     * @return ValueExpressionResult
     */
    private function unnamedEnumListResponse(): array
    {
        return $this->responses->enumList([...ValueResult::unknown(), 'type' => 'unknown[]']);
    }

    /**
     * `new SomeCollection($payload)` or `SomeCollection::make($payload)`, as its response over that payload.
     *
     * @param  class-string<ResourceCollection>  $className
     * @return ValueExpressionResult|null
     */
    private function namedCollection(
        StaticCall|New_ $call,
        string $className,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?ResponseExtras $added,
    ): ?array {
        $result = $this->responses->namedCollection(
            $className,
            $this->payloadPaginators($this->resourcePayloadArguments($call, $className)->at(0)?->value, $scope, $engine),
            $added,
        );

        return $result === null ? null : [...$result, 'optional' => $this->payloadIsConditional($call, $className)];
    }

    /**
     * One resource object as `new X()` or `X::make()` builds it: its response, which only filter() makes null.
     *
     * @param  class-string<JsonResource>  $className
     * @return ValueExpressionResult|null
     */
    private function singleResource(
        StaticCall|New_ $call,
        string $className,
        AnalysisScope $scope,
        ExpressionEngine $engine,
        ?ResponseExtras $added,
    ): ?array {
        $instance = $this->responses->single($className, $added);

        if ($instance === null) {
            return null;
        }

        $result = [...$instance, 'optional' => $this->payloadIsConditional($call, $className)];
        $type = $this->filteredType($call, $className, $result['type'], $scope, $engine);

        return $type === 'null'
            ? [...ValueResult::unknown(), 'type' => 'null', 'optional' => $result['optional']]
            : [...$result, 'type' => $type];
    }

    /**
     * Whether a construction's payload is one of the `$this->when*()` family, which can leave the key out.
     */
    private function payloadIsConditional(StaticCall|New_ $call, string $className): bool
    {
        return $call instanceof New_ ? $this->hasConditionalNewArgument($call, $className) : $this->hasConditionalArgument($call, $className);
    }

    /**
     * Whether Inertia sends a class as one resource object's response: an API resource, but not the EnumResource,
     * whose own empty `$wrap` sends the enum bare.
     *
     * @phpstan-assert-if-true class-string<JsonResource> $className
     */
    private function sendsResourceObject(string $className): bool
    {
        return $this->isResourceClass($className) && ! $this->isEnumResourceClass($className);
    }

    /**
     * Whether `SomeResource::collection()` builds an anonymous collection of that class: an API resource that is
     * neither a collection, whose items it would nest a second time, nor the EnumResource, whose items are enum cases.
     *
     * @phpstan-assert-if-true class-string<JsonResource> $className
     */
    private function collectsAnonymously(string $className): bool
    {
        return $this->isResourceClass($className)
            && ! $this->isEnumResourceClass($className)
            && ! is_a($className, ResourceCollection::class, true);
    }

    /**
     * The `@tolki/types` paginator kinds a collection's payload may hold, with `null` for an unpaginated one: from the
     * classes the receiver resolver names, else the payload's type, else the paginator a paginator's step is taken on.
     *
     * @return PayloadPaginators
     */
    private function payloadPaginators(?Expr $payload, AnalysisScope $scope, ExpressionEngine $engine): array
    {
        if ($payload === null) {
            return [null];
        }

        $held = resolve(ReceiverClassResolver::class)->resolve($payload, $scope);
        $kinds = $held === null ? null : $this->heldPaginators($held);

        if ($kinds !== null) {
            return $kinds;
        }

        $typed = $this->paginatorsOf($engine->resolve($payload)['type']);
        // A setter on a paginator the resolver reads only by a contract that lacks it hands back that same paginator.
        $page = $held === null && $typed === [null] ? PaginatorSteps::steppedFrom($payload) : null;

        return $page === null ? $typed : $this->payloadPaginators($page, $scope, $engine);
    }

    /**
     * The paginator kind of each class a payload may be an instance of, in order and once each, `null` for one that is
     * no paginator; null where a class may or may not be one, or either of two kinds, as the `Paginator` contract may.
     *
     * @return PayloadPaginators|null
     */
    private function heldPaginators(ReceiverType $held): ?array
    {
        $paginators = [];

        foreach ($held->classes as $class) {
            $paginator = array_find(PaginatorSteps::KINDS, static fn (string $paginator): bool => is_a($class, $paginator, true));

            // A class a built paginator extends or implements may hold one of either kind, or none.
            if ($paginator === null && array_any(PaginatorSteps::BUILT, static fn (string $built): bool => is_a($built, $class, true))) {
                return null;
            }

            $kind = $paginator === null ? null : TolkiTypes::MAP[$paginator];
            $paginators[$kind ?? ''] = $kind;
        }

        return array_values($paginators);
    }

    /**
     * The `@tolki/types` paginator each arm of a type names, in order and once each, with `null` once for any arm that
     * is no paginator, so a value that is paginated only sometimes publishes both responses.
     *
     * A `null` arm is no unpaginated one: a collection given null throws, and a `?->` adds its `| null` itself.
     *
     * @return PayloadPaginators
     */
    private function paginatorsOf(string $type): array
    {
        $paginators = [];

        foreach (TsTypeString::splitTopLevelUnion(ValueResult::stripNullArm($type)) as $arm) {
            $paginator = $this->paginatorName($arm);
            $paginators[$paginator ?? ''] = $paginator;
        }

        return $paginators === [] ? [null] : array_values($paginators);
    }

    /**
     * The `@tolki/types` paginator a type names, or null when it is not one.
     */
    private function paginatorName(string $type): ?string
    {
        foreach (array_keys(TolkiTypes::RESOURCE_PAGINATION) as $paginator) {
            if (str_starts_with($type, $paginator.'<')) {
                return $paginator;
            }
        }

        return null;
    }
}
