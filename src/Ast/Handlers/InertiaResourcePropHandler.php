<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\GuessesModelResources;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesEnumPropertyArgTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\ResolvesModelRelationTypes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ReceiverClassResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollectionBody;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceResponseBody;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResolver;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
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

/**
 * A resource a controller hands to `Inertia::render()` or a middleware shares, at any depth of the props, is sent as
 * its `toResponse()` data rather than the value a nested resource key flattens it to: its body inside the live wrap
 * key, and for a collection the pagination members of each paginator kind its payload may hold.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 * @phpstan-import-type CollectedElement from ResourceCollections
 * @phpstan-import-type GuessedCollection from GuessesModelResources
 *
 * @phpstan-type PayloadPaginators = non-empty-list<string|null>
 *
 * @internal
 */
final class InertiaResourcePropHandler implements ExpressionHandler
{
    use GuessesModelResources;
    use InspectsAstNodes;
    use InspectsResourceCalls;
    use ResolvesEnumPropertyArgTypes;
    use ResolvesModelRelationTypes;

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, New_::class, MethodCall::class, NullsafeMethodCall::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        return match (true) {
            $expr instanceof New_ => $this->resolveNew($expr, $scope, $engine),
            $expr instanceof StaticCall => $this->resolveStatic($expr, $scope, $engine),
            $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall => $this->resolveMethodCall($expr, $scope, $engine),
            default => null,
        };
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
     * `new SomeResource($model)` or `new SomeCollection($paginatorOrCollection)`.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveNew(New_ $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $expr->class instanceof Name) {
            return null;
        }

        $className = $this->subjectClassName($expr->class, $scope->subjectReflection);

        if (is_a($className, ResourceCollection::class, true)) {
            return $this->namedCollection($expr, $className, $engine);
        }

        return $this->sendsResourceObject($className) ? $this->singleResource($expr, $className) : null;
    }

    /**
     * `SomeResource::make($model)`, `SomeCollection::make($items)`, `SomeResource::collection($items)` and
     * `EnumResource::collection($cases)`.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveStatic(StaticCall $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $className = $this->resolveStaticCallClassName($expr);

        if ($className === null || ! $expr->name instanceof Identifier || $expr->isFirstClassCallable()) {
            return null;
        }

        if ($expr->name->toString() === 'collection') {
            return $this->resolveStaticCollection($expr, $className, $scope, $engine);
        }

        if ($expr->name->toString() !== 'make') {
            return null;
        }

        // make() is `new static(...$parameters)`, so it is sent exactly as the `new` it spells.
        if (is_a($className, ResourceCollection::class, true)) {
            return $this->namedCollection($expr, $className, $engine);
        }

        return $this->sendsResourceObject($className) ? $this->singleResource($expr, $className) : null;
    }

    /**
     * `$model->toResource()` or `$items->toResourceCollection()`, with or without a class, through `->` or `?->`.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveMethodCall(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if (! $call->name instanceof Identifier) {
            return null;
        }

        $result = match ($call->name->toString()) {
            'toResource' => $this->resolveToResource($call, $scope),
            'toResourceCollection' => $this->resolveToResourceCollection($call, $scope, $engine),
            default => null,
        };

        // A `?->` here or earlier in the chain ends the whole expression as null, where a plain `->` on null throws.
        return $result !== null && $this->chainShortCircuits($call)
            ? [...$result, 'type' => ValueResult::withNullArm($result['type'])]
            : $result;
    }

    /**
     * `$model->toResource()` or `$model->toResource(SomeResource::class)`: each resource it may build, as sent.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveToResource(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): ?array
    {
        $value = $this->analyzeToResourceCall($call, $scope);
        $responses = [];

        foreach (isset($value['resourceFqcn']) ? [$value['resourceFqcn']] : ($value['embeddedResourceFqcns'] ?? []) as $resource) {
            if (! $this->sendsResourceObject($resource)) {
                return null;
            }

            $responses[] = $this->instanceResponse($resource);
        }

        return $responses === [] ? null : [...$value, 'type' => implode(' | ', $responses)];
    }

    /**
     * `$items->toResourceCollection()`, the collection Laravel guesses from the items' model, or
     * `->toResourceCollection(SomeResource::class)`, that resource's anonymous collection; each over the paginator the
     * receiver may be.
     *
     * @return ValueExpressionResult|null null for a receiver whose model, or a class, it cannot read
     */
    private function resolveToResourceCollection(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $args = $this->resourceCallArguments($call, Collection::class, 'toResourceCollection');
        $explicitArg = $args->named('resourceClass') ?? $args->at(0);
        $receiver = $engine->resolve($call->var);
        $paginators = $this->paginatorsOf($receiver['type']);

        if ($explicitArg !== null) {
            $explicit = resolve(ValueResolver::class)->resolveClassConstArgument($explicitArg->value);

            // Laravel calls `$explicit::collection($items)`.
            return $explicit !== null && $this->collectsAnonymously($explicit)
                ? $this->anonymousCollectionType($explicit, $paginators)
                : null;
        }

        $guessed = $this->guessedCollection($call->var, $receiver, $scope);

        if ($guessed === null) {
            return null;
        }

        if ($guessed['collection'] !== null) {
            return $this->namedCollectionType($guessed['collection'], $paginators);
        }

        return $this->collectsAnonymously($guessed['resource']) ? $this->anonymousCollectionType($guessed['resource'], $paginators) : null;
    }

    /**
     * The collection Laravel's guess builds for the items of a `toResourceCollection()` receiver, from their model: a
     * list or paginator of it, or an Eloquent collection of it the receiver resolver names.
     *
     * @param  ValueExpressionResult  $receiver  the receiver as the engine resolves it
     * @return GuessedCollection|null
     */
    private function guessedCollection(Expr $var, array $receiver, AnalysisScope $scope): ?array
    {
        $listsModel = isset($receiver['modelFqcn']) && array_all(
            TsTypeString::splitTopLevelUnion($receiver['type']),
            fn (string $arm): bool => str_ends_with($arm, '[]') || $this->paginatorName($arm) !== null,
        );

        $model = $listsModel ? $receiver['modelFqcn'] : resolve(ReceiverClassResolver::class)->resolve($var, $scope)?->elementModel;

        return $model !== null && is_a($model, Model::class, true) ? $this->resolveResourceCollectionForModel($model) : null;
    }

    /**
     * `SomeResource::collection($paginatorOrCollection)` — an anonymous collection of that resource.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveStaticCollection(StaticCall $expr, string $className, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($this->isEnumResourceClass($className)) {
            return $this->enumResourceCollection($expr, $className, $scope, $engine);
        }

        if (! $this->collectsAnonymously($className)) {
            return null;
        }

        return [
            ...$this->anonymousCollectionType(
                $className,
                $this->payloadPaginators($this->resourcePayloadArguments($expr, $className)->at(0), $engine),
            ),
            'optional' => $this->hasConditionalArgument($expr, $className),
        ];
    }

    /**
     * `EnumResource::collection($cases)`: the enum list inside the anonymous collection's live wrap key.
     *
     * @return ValueExpressionResult|null null when no single enum resolves, as the nested value would not either
     */
    private function enumResourceCollection(StaticCall $expr, string $className, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        $payload = $this->resourcePayloadArguments($expr, $className)->at(0)?->value;
        $list = $payload === null ? null : $this->enumCollectionValue($payload, $scope, $engine);

        return $list === null ? null : [...$list, 'type' => $this->response(AnonymousResourceCollection::class, $list['type'])];
    }

    /**
     * `new SomeCollection($payload)` or `SomeCollection::make($payload)`, as its response over that payload.
     *
     * @param  class-string<ResourceCollection>  $className
     * @return ValueExpressionResult|null
     */
    private function namedCollection(StaticCall|New_ $call, string $className, ExpressionEngine $engine): ?array
    {
        $result = $this->namedCollectionType(
            $className,
            $this->payloadPaginators($this->resourcePayloadArguments($call, $className)->at(0), $engine),
        );

        return $result === null ? null : [...$result, 'optional' => $this->payloadIsConditional($call, $className)];
    }

    /**
     * A named collection's response over each paginator kind its payload may hold, and unpaginated when it may hold
     * none: its body inside the live wrap key, beside that kind's pagination members.
     *
     * @param  class-string<ResourceCollection>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult|null null for a flat one over a paginator that collects no element
     */
    private function namedCollectionType(string $className, array $paginators): ?array
    {
        // The prop follows the collection's wrap, element and body, which no file the page records holds.
        DependencyRecorder::recordClass($className);

        $name = TsNaming::resourceTypeName($className);
        $kinds = $this->paginatorKinds($paginators);

        if ($kinds === []) {
            return [...ValueResult::unknown(), 'type' => $this->response($className, $name, $className), 'resourceFqcn' => $className];
        }

        $unpaginated = in_array(null, $paginators, true) ? [$this->response($className, $name, $className)] : [];
        $reflection = self::reflect($className);

        // Without a wrap key, a collection that sends its collected list IS the paginator's data: it is the paginator.
        if (ResourceWrapping::key($className) === null && resolve(ResourceCollectionBody::class)->sendsCollectedList($reflection)) {
            $element = ResourceCollections::collectedElement($reflection);

            if ($element === null) {
                return null;
            }

            $paginated = $this->paginatedResourceType($element, $reflection, null, $kinds);

            return $unpaginated === [] ? $paginated : [
                ...$paginated,
                'type' => $this->union([...$unpaginated, $paginated['type']]),
                'embeddedResourceFqcns' => [$className],
            ];
        }

        $blocks = $this->paginationNames($kinds, 'blocks');

        return [
            ...ValueResult::unknown(),
            'type' => $this->union([...$unpaginated, $this->beside($this->response($className, $name, $className, paginated: true), $blocks)]),
            'resourceFqcn' => $className,
            'customImports' => ['@tolki/types' => $blocks],
        ];
    }

    /**
     * An anonymous collection's response over each paginator kind its payload may hold, and unpaginated when it may
     * hold none: its list inside the anonymous collection's live wrap key, or each kind's paginator response.
     *
     * @param  class-string<JsonResource>  $className
     * @param  PayloadPaginators  $paginators
     * @return ValueExpressionResult
     */
    private function anonymousCollectionType(string $className, array $paginators): array
    {
        $reflection = self::reflect($className);
        $name = TsNaming::resourceTypeName($className);
        // The anonymous collection wraps by its own `$wrap`, `JsonResource`'s shared slot, never the collected one's.
        $wrapKey = ResourceWrapping::key(AnonymousResourceCollection::class);
        $kinds = $this->paginatorKinds($paginators);
        $types = [];
        $imports = [];

        if (in_array(null, $paginators, true)) {
            if ($wrapKey === 'data' && ! ResourceCollections::preservesKeys($reflection)) {
                $types[] = "AnonymousResourceCollection<{$name}>";
                $imports[] = 'AnonymousResourceCollection';
            } else {
                $types[] = $this->response(AnonymousResourceCollection::class, ResourceCollections::listType($name, $reflection));
            }
        }

        if ($kinds !== []) {
            $paginated = $this->paginatedResourceType(['fqcn' => $className, 'model' => false], $reflection, $wrapKey, $kinds);
            $types[] = $paginated['type'];
            $imports = [...$imports, ...$paginated['customImports']['@tolki/types'] ?? []];
        }

        return [
            ...ValueResult::unknown(),
            'type' => $this->union($types),
            'resourceFqcn' => $className,
            ...($imports === [] ? [] : ['customImports' => ['@tolki/types' => $imports]]),
        ];
    }

    /**
     * The paginator response of an anonymous or flat collection over each kind it may wrap: its items under the
     * response's wrap key, `data` without one, keyed when the reflected class preserves keys.
     *
     * @param  CollectedElement  $element
     * @param  ReflectionClass<object>  $reflection  the class whose preserve-keys state applies
     * @param  non-empty-list<string>  $kinds
     * @return ValueExpressionResult
     */
    private function paginatedResourceType(array $element, ReflectionClass $reflection, ?string $wrapKey, array $kinds): array
    {
        $item = ResourceCollections::elementValue($element);
        $list = ResourceCollections::listType($item['type'], $reflection);
        $key = $wrapKey ?? 'data';

        if ($key !== 'data') {
            $blocks = $this->paginationNames($kinds, 'blocks');

            return [...$item, 'type' => $this->beside($this->nested($key, $list), $blocks), 'customImports' => ['@tolki/types' => $blocks]];
        }

        $responses = $this->paginationNames($kinds, 'response');
        $keyed = ResourceCollections::preservesKeys($reflection);
        $types = array_map(
            // The response's own `data: T[]` cannot stand beside a keyed list, so the list replaces it.
            fn (string $response): string => $keyed
                ? "Omit<{$response}<{$item['type']}>, 'data'> & ".$this->nested('data', $list)
                : "{$response}<{$item['type']}>",
            $responses,
        );

        return [...$item, 'type' => $this->union($types), 'customImports' => ['@tolki/types' => $responses]];
    }

    /**
     * One resource object, as `new X()` or `X::make()` builds it: its response, an object even over a null payload.
     *
     * @param  class-string<JsonResource>  $className
     * @return ValueExpressionResult
     */
    private function singleResource(StaticCall|New_ $call, string $className): array
    {
        return [
            ...ValueResult::unknown(),
            'type' => $this->instanceResponse($className),
            'optional' => $this->payloadIsConditional($call, $className),
            'resourceFqcn' => $className,
        ];
    }

    /**
     * What one resource object's response sends: its own type, inside its class's live wrap key.
     *
     * @param  class-string<JsonResource>  $resource
     */
    private function instanceResponse(string $resource): string
    {
        return $this->response($resource, TsNaming::resourceTypeName($resource), $resource);
    }

    /**
     * What Laravel's response sends for a body, by the live wrap key of the class it wraps by: the body nested under
     * that key unless it already holds it, both when it holds it on some paths only, and the body bare without a key,
     * except beside a paginator's members, which nest it under `data`.
     *
     * @param  class-string<JsonResource>  $wrapsBy
     * @param  class-string<JsonResource>|null  $publishedBy  the class whose own type the body is; null for a list
     */
    private function response(string $wrapsBy, string $body, ?string $publishedBy = null, bool $paginated = false): string
    {
        $key = ResourceWrapping::key($wrapsBy);

        if ($key === null) {
            return $paginated ? $this->nested('data', $body) : $body;
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
     * Types as the arms of one union, each intersection grouped so the union reads as its arms.
     *
     * @param  list<string>  $types
     */
    private function union(array $types): string
    {
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
     * whose own empty `$wrap` sends the enum bare, nor a JSON:API resource, whose response is a document.
     *
     * @phpstan-assert-if-true class-string<JsonResource> $className
     */
    private function sendsResourceObject(string $className): bool
    {
        $jsonApi = 'Illuminate\Http\Resources\JsonApi\JsonApiResource';

        return $this->isResourceClass($className)
            && ! $this->isEnumResourceClass($className)
            && ! (class_exists($jsonApi) && is_a($className, $jsonApi, true));
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
     * @return PayloadPaginators
     */
    private function paginatorsOf(string $type): array
    {
        $paginators = [];

        foreach (TsTypeString::splitTopLevelUnion($type) as $arm) {
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
