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
use AbeTwoThree\LaravelTsPublish\Ast\Receivers\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\EmptyReceiverGuess;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\JsonApiResources;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResourceResponseBody;
use AbeTwoThree\LaravelTsPublish\Ast\Resources\ResponseExtras;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use AbeTwoThree\LaravelTsPublish\Support\TolkiTypes;
use AbeTwoThree\LaravelTsPublish\Support\TsTypeShape;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use Illuminate\Support\Collection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;
use ReflectionMethod;

/**
 * A resource a controller hands to `Inertia::render()` or a middleware shares, at any depth of the props, is sent as
 * its `toResponse()` data, not the value a nested resource key flattens it to: its body inside the live wrap key, a
 * collection's pagination members for each paginator its payload may hold, and the `with()` and `additional()` keys.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type GuessedCollection from GuessesModelResources
 *
 * @phpstan-type PayloadPaginators = non-empty-list<string|null>
 * @phpstan-type JsonApiIncluded = array{arguments: string, resources: list<class-string>, imports: list<string>}
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

    /** Where a paginated response nests its list with no live wrap key, as its pagination members sit beside it. */
    private const PAGINATED_LIST_KEY = 'data';

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, New_::class, MethodCall::class, NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        return $this->resolveBuilt($expr, $scope, $engine);
    }

    /**
     * Re-reflect by name so the invariant ReflectionClass template widens back to <object>.
     *
     * @param  class-string  $className
     * @return ReflectionClass<object>
     */
    private static function reflect(string $className): ReflectionClass
    {
        return new ReflectionClass($className);
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
            return $this->namedCollection($expr, $className, $engine, $added);
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
            return $this->namedCollection($expr, $className, $engine, $added);
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
            $response = $this->sendsResourceObject($resource) ? $this->instanceResult($resource, $added) : null;

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
                $this->collectsAnonymously($explicit) => $this->anonymousCollectionType(
                    $explicit,
                    $this->paginatorsOf($engine->resolve($call->var)['type']),
                    $added,
                ),
                default => null,
            };
        }

        $receiver = $engine->resolve($call->var);
        $paginators = $this->paginatorsOf($receiver['type']);
        $guessed = $this->guessedCollection($call->var, $receiver, $scope);

        if ($guessed === null) {
            return null;
        }

        if ($guessed['collection'] !== null) {
            $named = $this->namedCollectionType($guessed['collection'], $paginators, $added);

            return $named === null || resolve(EmptyReceiverGuess::class)->responseAdmitsEmpty($guessed['collection'])
                ? $named
                : $this->besideEmptyReceiver($named, $paginators, $added);
        }

        return $this->collectsAnonymously($guessed['resource'])
            ? $this->anonymousCollectionType($guessed['resource'], $paginators, $added)
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

        $result = $this->collectsAnonymously($className) ? $this->anonymousCollectionType(
            $className,
            $this->payloadPaginators($this->resourcePayloadArguments($expr, $className)->at(0), $engine),
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

        return $list === null ? null : [...$list, 'type' => $this->response(AnonymousResourceCollection::class, $list['type'])];
    }

    /**
     * What an EnumResource collection sends over items naming no enum, whose nested value names an unpublished type.
     *
     * @return ValueExpressionResult
     */
    private function unnamedEnumListResponse(): array
    {
        return [...ValueResult::unknown(), 'type' => $this->response(AnonymousResourceCollection::class, 'unknown[]')];
    }

    /**
     * `new SomeCollection($payload)` or `SomeCollection::make($payload)`, as its response over that payload.
     *
     * @param  class-string<ResourceCollection>  $className
     * @return ValueExpressionResult|null
     */
    private function namedCollection(StaticCall|New_ $call, string $className, ExpressionEngine $engine, ?ResponseExtras $added): ?array
    {
        $result = $this->namedCollectionType(
            $className,
            $this->payloadPaginators($this->resourcePayloadArguments($call, $className)->at(0), $engine),
            $added,
        );

        return $result === null ? null : [...$result, 'optional' => $this->payloadIsConditional($call, $className)];
    }

    /**
     * A named collection's response over each paginator kind its payload may hold, and unpaginated when it may hold
     * none: its body inside the live wrap key, beside that kind's pagination members and its `with()` keys.
     *
     * @param  class-string<ResourceCollection>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult|null null for a flat one over a paginator that collects no element
     */
    private function namedCollectionType(string $className, array $paginators, ?ResponseExtras $added = null): ?array
    {
        // The prop follows the collection's wrap, element and body, which no file the page records holds.
        DependencyRecorder::recordClass($className);

        // Laravel merges with() and additional() into the response once, so both join it in one step.
        $extras = ResponseExtras::withOf($className)->beside($added);
        $bodyKey = ResourceWrapping::responseKey($className, true);
        $sent = $extras->sent;
        $name = TsNaming::resourceTypeName($className);
        $kinds = $this->paginatorKinds($paginators);

        if ($kinds === []) {
            return $extras->appliedTo([
                ...ValueResult::unknown(),
                'type' => $this->response($className, $name, $className, extras: $sent),
                'resourceFqcn' => $className,
            ], $bodyKey);
        }

        $unpaginated = in_array(null, $paginators, true) ? [$this->response($className, $name, $className, extras: $sent)] : [];
        $reflection = self::reflect($className);

        // Without a wrap key, a collection that sends its collected list IS the paginator's data: it is the paginator.
        if (ResourceWrapping::key($className) === null && resolve(ResourceCollectionBody::class)->sendsCollectedList($reflection)) {
            $element = ResourceCollections::collectedElement($reflection);

            if ($element === null) {
                return null;
            }

            $paginated = $this->paginatedResourceType(ResourceCollections::elementValue($element), $reflection, null, $kinds);

            return $extras->appliedTo($unpaginated === [] ? $paginated : [
                ...$paginated,
                'type' => $this->union([...$unpaginated, $paginated['type']]),
                'embeddedResourceFqcns' => [$className],
            ], $bodyKey);
        }

        $blocks = $this->paginationNames($kinds, 'blocks');

        return $extras->appliedTo([
            ...ValueResult::unknown(),
            'type' => $this->union([...$unpaginated, $this->beside($this->response($className, $name, $className, paginated: true), $blocks)]),
            'resourceFqcn' => $className,
            'customImports' => [TolkiTypes::MODULE => $blocks],
        ], $bodyKey);
    }

    /**
     * An anonymous collection's response over each paginator kind its payload may hold, and unpaginated when it may
     * hold none: its list inside the anonymous collection's live wrap key, or each kind's paginator response.
     *
     * @param  class-string<JsonResource>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult|null null for a JSON:API one an `additional()` call adds keys to
     */
    private function anonymousCollectionType(string $className, array $paginators, ?ResponseExtras $added = null): ?array
    {
        if (JsonApiResources::is($className)) {
            return $added === null ? $this->jsonApiCollection($className, $paginators) : null;
        }

        $reflection = self::reflect($className);
        $name = TsNaming::resourceTypeName($className);
        // The anonymous collection wraps by its own `$wrap`, `JsonResource`'s shared slot, never the collected one's.
        $wrapKey = ResourceWrapping::key(AnonymousResourceCollection::class);
        $kinds = $this->paginatorKinds($paginators);
        $types = [];
        $imports = [];

        if (in_array(null, $paginators, true)) {
            if ($wrapKey === 'data' && ! ResourceCollections::preservesKeys($reflection)) {
                $anonymous = TolkiTypes::MAP[AnonymousResourceCollection::class];
                $types[] = "{$anonymous}<{$name}>";
                $imports[] = $anonymous;
            } else {
                $types[] = $this->response(
                    AnonymousResourceCollection::class,
                    ResourceCollections::listType($name, $reflection),
                    extras: $added === null ? false : $added->sent,
                );
            }
        }

        if ($kinds !== []) {
            $item = ResourceCollections::elementValue(['fqcn' => $className, 'model' => false]);
            $paginated = $this->paginatedResourceType($item, $reflection, $wrapKey, $kinds);
            $types[] = $paginated['type'];
            $imports = [...$imports, ...$paginated['customImports'][TolkiTypes::MODULE] ?? []];
        }

        $result = [
            ...ValueResult::unknown(),
            'type' => $this->union($types),
            'resourceFqcn' => $className,
            ...($imports === [] ? [] : ['customImports' => [TolkiTypes::MODULE => $imports]]),
        ];

        return $added === null ? $result : $added->appliedTo($result, ResourceWrapping::responseKey(AnonymousResourceCollection::class, true));
    }

    /**
     * A guessed collection's response beside the one an empty receiver sends instead: the plain collection's, its empty
     * list inside that collection's live wrap key, or each paginator kind's response over no items.
     *
     * @param  ValueExpressionResult  $named
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult
     */
    private function besideEmptyReceiver(array $named, array $paginators, ?ResponseExtras $added): array
    {
        $collection = EmptyReceiverGuess::COLLECTION;
        $kinds = $this->paginatorKinds($paginators);
        $sent = $added === null ? false : $added->sent;
        $types = in_array(null, $paginators, true) ? [$this->response($collection, EmptyReceiverGuess::listType(), extras: $sent)] : [];
        $imports = $named['customImports'][TolkiTypes::MODULE] ?? [];

        if ($kinds !== []) {
            $item = [...ValueResult::unknown(), 'type' => EmptyReceiverGuess::ELEMENT];
            $paginated = $this->paginatedResourceType($item, self::reflect($collection), ResourceWrapping::key($collection), $kinds);
            $types[] = $paginated['type'];
            $imports = [...$imports, ...$paginated['customImports'][TolkiTypes::MODULE] ?? []];
        }

        // The named arm carries the added keys' classes already; each empty arm only spells them again.
        $bodyKey = ResourceWrapping::responseKey($collection, true);
        $types = $added === null ? $types : array_map(
            static fn (string $type): string => $added->appliedTo([...ValueResult::unknown(), 'type' => $type], $bodyKey)['type'],
            $types,
        );

        return [
            ...$named,
            'type' => $this->union([$named['type'], ...$types]),
            ...($imports === [] ? [] : ['customImports' => [TolkiTypes::MODULE => array_values(array_unique($imports))]]),
        ];
    }

    /**
     * The paginator response of an anonymous or flat collection over each kind it may wrap: its items under the
     * response's wrap key, `data` without one, keyed when the reflected class preserves keys.
     *
     * @param  ValueExpressionResult  $item  what one collected item publishes as
     * @param  ReflectionClass<object>  $reflection  the class whose preserve-keys state applies
     * @param  non-empty-list<string>  $kinds
     * @return ValueExpressionResult
     */
    private function paginatedResourceType(array $item, ReflectionClass $reflection, ?string $wrapKey, array $kinds): array
    {
        $list = ResourceCollections::listType($item['type'], $reflection);
        $key = $this->paginatedKey($wrapKey);

        if ($key !== 'data') {
            $blocks = $this->paginationNames($kinds, 'blocks');

            return [...$item, 'type' => $this->beside($this->nested($key, $list), $blocks), 'customImports' => [TolkiTypes::MODULE => $blocks]];
        }

        $responses = $this->paginationNames($kinds, 'response');
        $keyed = ResourceCollections::preservesKeys($reflection);
        $types = array_map(
            fn (string $response): string => $keyed
                ? $this->replacingData("{$response}<{$item['type']}>", 'data', $list)
                : "{$response}<{$item['type']}>",
            $responses,
        );

        return [...$item, 'type' => $this->union($types), 'customImports' => [TolkiTypes::MODULE => $responses]];
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
        $instance = $this->instanceResult($className, $added);

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
     * What one resource object's response sends, with the classes it names: a JSON:API resource's document, else its
     * own type inside its class's live wrap key, beside its `with()` keys.
     *
     * @param  class-string<JsonResource>  $resource
     * @return ValueExpressionResult|null null for a JSON:API one an `additional()` call adds keys to
     */
    private function instanceResult(string $resource, ?ResponseExtras $added = null): ?array
    {
        if (JsonApiResources::is($resource)) {
            $document = TolkiTypes::JSON_API_DOCUMENTS['single'];
            $included = $this->jsonApiIncluded($resource);

            return $added === null ? $this->jsonApiResult($resource, $document.$included['arguments'], [$document], $included) : null;
        }

        $extras = ResponseExtras::withOf($resource)->beside($added);

        return $extras->appliedTo([
            ...ValueResult::unknown(),
            'type' => $this->response($resource, TsNaming::resourceTypeName($resource), $resource, extras: $extras->sent),
            'resourceFqcn' => $resource,
        ], ResourceWrapping::responseKey($resource, true));
    }

    /**
     * A JSON:API resource's collection over each paginator kind its payload may hold, and unpaginated when it may hold
     * none: each kind's `@tolki/types` document, the bare list where the anonymous collection's live wrap key is empty
     * and nothing is paginated, and the list under any other key beside the document's other members.
     *
     * @param  class-string<JsonResource>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult
     */
    private function jsonApiCollection(string $className, array $paginators): array
    {
        $reflection = self::reflect($className);
        $list = ResourceCollections::listType(TsNaming::resourceTypeName($className), $reflection);
        $keyed = ResourceCollections::preservesKeys($reflection);
        // JSON:API's anonymous collection does not redeclare `$wrap`, so it shares JsonResource's slot.
        $wrapKey = ResourceWrapping::key(AnonymousResourceCollection::class);
        $keys = in_array(null, $paginators, true) ? [TolkiTypes::JSON_API_DOCUMENTS['collection'] => $wrapKey] : [];

        foreach ($this->paginatorKinds($paginators) as $kind) {
            $keys[TolkiTypes::RESOURCE_PAGINATION[$kind]['jsonApi']] = $this->paginatedKey($wrapKey);
        }

        $included = $this->jsonApiIncluded($className);
        $types = [];
        $documents = [];

        foreach ($keys as $document => $key) {
            if ($key === null) {
                $types[] = $list;

                continue;
            }

            $generic = $document.$included['arguments'];
            $types[] = $key === 'data' && ! $keyed ? $generic : $this->replacingData($generic, $key, $list);
            $documents[] = $document;
        }

        return $this->jsonApiResult($className, $this->union($types), $documents, $included);
    }

    /**
     * The generic arguments of a JSON:API resource's `@tolki/types` documents, `<R, I>`: `I` is each resource object an
     * include can add, a published JSON:API resource by its own type and any other by its `type` over the generic one;
     * `never` for a resource declaring no relationship, and left to its default where an include may add one unread.
     *
     * @param  class-string<JsonResource>  $className
     * @return JsonApiIncluded
     */
    private function jsonApiIncluded(string $className): array
    {
        $members = [];
        $resources = [];
        $imports = [];

        foreach (JsonApiResources::includedResources($className) as $included) {
            if ($included['resource'] !== null && PublishedClasses::exports($included['resource'])) {
                $members[] = TsNaming::resourceTypeName($included['resource']);
                $resources[] = $included['resource'];

                continue;
            }

            $object = TolkiTypes::JSON_API_DOCUMENTS['resourceObject'];
            $members[] = $included['type'] === 'string' ? $object : "{$object}<{$included['type']}>";
            $imports[] = $object;
        }

        if ($members === [] && JsonApiResources::declaresNoRelationships($className)) {
            $members[] = 'never';
        }

        $name = TsNaming::resourceTypeName($className);

        return [
            'arguments' => $members === [] ? "<{$name}>" : "<{$name}, ".implode(' | ', array_values(array_unique($members))).'>',
            'resources' => $resources,
            'imports' => array_values(array_unique($imports)),
        ];
    }

    /**
     * A JSON:API resource's prop type with the classes and `@tolki/types` names it spells; the included ones only
     * where a document spells its generic arguments.
     *
     * @param  class-string<JsonResource>  $className
     * @param  list<string>  $documents  the document names the type spells
     * @param  JsonApiIncluded  $included
     * @return ValueExpressionResult
     */
    private function jsonApiResult(string $className, string $type, array $documents, array $included): array
    {
        // The prop follows the relationships of the resource and of each one an include reaches, read off their files.
        DependencyRecorder::recordClass($className);

        $spelled = $documents === [] ? ['resources' => [], 'imports' => []] : $included;
        $imports = [...$documents, ...$spelled['imports']];

        return [
            ...ValueResult::unknown(),
            'type' => $type,
            'resourceFqcn' => $className,
            ...($spelled['resources'] === [] ? [] : ['embeddedResourceFqcns' => $spelled['resources']]),
            ...($imports === [] ? [] : ['customImports' => [TolkiTypes::MODULE => $imports]]),
        ];
    }

    /**
     * What Laravel's response sends for a body, by the live wrap key of the class it wraps by: the body nested under
     * that key unless it already holds it, both when it holds it on some paths only, and the body bare without a key,
     * except beside a paginator's members or a `with()` or `additional()` key, which nest it under `data`.
     *
     * @param  class-string<JsonResource>  $wrapsBy
     * @param  class-string<JsonResource>|null  $publishedBy  the class whose own type the body is; null for a list
     * @param  bool|null  $extras  whether a key is sent beside the body: always, never or on some paths (null)
     */
    private function response(
        string $wrapsBy,
        string $body,
        ?string $publishedBy = null,
        bool $paginated = false,
        ?bool $extras = false,
    ): string {
        $key = ResourceWrapping::key($wrapsBy);

        if ($key === null && $paginated) {
            return $this->nested(self::PAGINATED_LIST_KEY, $body);
        }

        // With no live wrap key, Laravel nests the body beside any extra key, even a body that holds `data` itself.
        if ($key === null) {
            $nests = ResourceWrapping::responseKey($wrapsBy, $extras !== false);

            return match (true) {
                $nests === null => $body,
                $extras === null => $body.' | '.$this->nested($nests, $body),
                default => $this->nested($nests, $body),
            };
        }

        return match ($publishedBy === null ? false : resolve(ResourceResponseBody::class)->holdsWrapKey($publishedBy, $key)) {
            true => $body,
            false => $this->nested($key, $body),
            null => $body.' | '.$this->nested($key, $body),
        };
    }

    /**
     * A body nested under a response key: `{ <key>: <body> }`.
     */
    private function nested(string $key, string $body): string
    {
        return '{ '.JsEmitter::validJsObjectKey($key).': '.$body.' }';
    }

    /**
     * The key a paginated response nests its list under: the live wrap key, else `data`.
     */
    private function paginatedKey(?string $wrapKey): string
    {
        return $wrapKey ?? self::PAGINATED_LIST_KEY;
    }

    /**
     * A response type with its own `data` list replaced by the given list under the key: `Omit<T, 'data'> & { k: L }`.
     * A list under another key, or a keyed list, cannot sit beside the `data: T[]` the type declares.
     */
    private function replacingData(string $response, string $key, string $list): string
    {
        return "Omit<{$response}, 'data'> & ".$this->nested($key, $list);
    }

    /**
     * A body beside the pagination members of each paginator kind it may be sent over.
     *
     * @param  non-empty-list<string>  $blocks
     */
    private function beside(string $body, array $blocks): string
    {
        $grouped = static fn (string $type): string => count(TsTypeString::splitTopLevelUnion($type)) > 1 ? "({$type})" : $type;

        return $grouped($body).' & '.$grouped(implode(' | ', $blocks));
    }

    /**
     * Types as the arms of one union, once each, each intersection grouped so the union reads as its arms.
     *
     * @param  list<string>  $types
     */
    private function union(array $types): string
    {
        $types = array_values(array_unique($types));
        $grouped = count($types) > 1;

        return implode(' | ', array_map(
            static fn (string $type): string => $grouped && count(TsTypeShape::splitTopLevel($type, ['&'])) > 1 ? "({$type})" : $type,
            $types,
        ));
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
     * The `@tolki/types` paginator each arm of the payload argument's type names, see paginatorsOf().
     *
     * @return PayloadPaginators
     */
    private function payloadPaginators(?Arg $payload, ExpressionEngine $engine): array
    {
        return $payload === null ? [null] : $this->paginatorsOf($engine->resolve($payload->value)['type']);
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

    /**
     * The paginators of a payload, without the `null` that stands for its unpaginated arms.
     *
     * @param  PayloadPaginators  $paginators
     * @return list<string>
     */
    private function paginatorKinds(array $paginators): array
    {
        return array_values(array_filter($paginators, static fn (?string $paginator): bool => $paginator !== null));
    }

    /**
     * The `@tolki/types` names of what each paginator kind sends with a resource collection: the pagination blocks a
     * body sits beside, or the whole response.
     *
     * @param  non-empty-list<string>  $kinds
     * @param  'blocks'|'response'  $part
     * @return non-empty-list<string>
     */
    private function paginationNames(array $kinds, string $part): array
    {
        return array_map(static fn (string $kind): string => TolkiTypes::RESOURCE_PAGINATION[$kind][$part], $kinds);
    }
}
