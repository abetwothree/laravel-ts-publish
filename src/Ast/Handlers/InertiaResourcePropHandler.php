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
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceResponseBody;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Facades\TsTypeString;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
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
 * key, and for a collection the pagination members its paginator adds.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
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

    /** @var list<string> The `@tolki/types` names ModelFinderHandler renders a paginator as. */
    private const PAGINATOR_NAMES = ['LengthAwarePaginator', 'SimplePaginator', 'CursorPaginator'];

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
            $expr instanceof MethodCall, $expr instanceof NullsafeMethodCall => $this->resolveToResource($expr, $scope),
            default => null,
        };
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
     * `$model->toResource()` or `$model?->toResource(SomeResource::class)`: each resource it may build, as sent.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveToResource(MethodCall|NullsafeMethodCall $call, AnalysisScope $scope): ?array
    {
        if (! $call->name instanceof Identifier || $call->name->toString() !== 'toResource') {
            return null;
        }

        $value = $this->analyzeToResourceCall($call, $scope);
        $responses = [];

        foreach (isset($value['resourceFqcn']) ? [$value['resourceFqcn']] : ($value['embeddedResourceFqcns'] ?? []) as $resource) {
            if (! $this->sendsResourceObject($resource)) {
                return null;
            }

            $responses[] = $this->instanceResponse($resource);
        }

        if ($responses === []) {
            return null;
        }

        $type = implode(' | ', $responses);

        // A `?->` here or earlier in the chain ends the whole expression as null, where a plain `->` on null throws.
        return [...$value, 'type' => $this->chainShortCircuits($call) ? ValueResult::withNullArm($type) : $type];
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

        if (! $this->isResourceClass($className) || is_a($className, ResourceCollection::class, true)) {
            return null;
        }

        /** @var class-string $className */
        $reflection = self::reflect($className);
        $optional = $this->hasConditionalArgument($expr, $className);

        // The anonymous collection wraps by its own `$wrap`, `JsonResource`'s shared slot, never the collected one's.
        $wrapKey = ResourceWrapping::key(AnonymousResourceCollection::class);

        if ($this->firstArgumentIsPaginator($this->resourcePayloadArguments($expr, $className)->at(0), $engine)) {
            return [
                ...ValueResult::unknown(),
                'type' => $this->paginatedResourceType($className, $reflection, $wrapKey ?? 'data'),
                'optional' => $optional,
                'resourceFqcn' => $className,
                'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
            ];
        }

        $name = TsNaming::resourceTypeName($className);

        if ($wrapKey === 'data') {
            return [
                ...ValueResult::unknown(),
                'type' => 'AnonymousResourceCollection<'.$name.'>',
                'optional' => $optional,
                'resourceFqcn' => $className,
                'customImports' => ['@tolki/types' => ['AnonymousResourceCollection']],
            ];
        }

        return [
            ...ValueResult::unknown(),
            'type' => $this->response(AnonymousResourceCollection::class, ResourceCollections::listType($name, $reflection)),
            'optional' => $optional,
            'resourceFqcn' => $className,
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
     * A named collection, typed by what it wraps: a paginator adds the pagination members, and a body that does not
     * hold the live wrap key is nested under it.
     *
     * @param  class-string<ResourceCollection>  $className
     * @return ValueExpressionResult|null
     */
    private function namedCollection(StaticCall|New_ $call, string $className, ExpressionEngine $engine): ?array
    {
        $reflection = self::reflect($className);
        $name = TsNaming::resourceTypeName($className);
        $optional = $this->payloadIsConditional($call, $className);

        if (! $this->firstArgumentIsPaginator($this->resourcePayloadArguments($call, $className)->at(0), $engine)) {
            return [
                ...ValueResult::unknown(),
                'type' => $this->response($className, $name, $className),
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // A body-less collection without a wrap key IS the paginator's data, so it becomes the paginator itself.
        if (ResourceWrapping::key($className) === null && ResourceCollections::runsFrameworkToArray($reflection)) {
            $element = ResourceCollections::collectedElement($reflection);

            return $element === null ? null : [
                ...ResourceCollections::elementValue($element),
                'type' => $this->paginatedResourceType($element['fqcn'], $reflection, 'data'),
                'optional' => $optional,
                'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
            ];
        }

        $response = $this->response($className, $name, $className, paginated: true);

        return [
            ...ValueResult::unknown(),
            'type' => (count(TsTypeString::splitTopLevelUnion($response)) > 1 ? '('.$response.')' : $response).' & ResourcePagination',
            'optional' => $optional,
            'resourceFqcn' => $className,
            'customImports' => ['@tolki/types' => ['ResourcePagination']],
        ];
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
     * The paginator response over a singular resource, its items under the key the response nests them in and keyed
     * when the reflected class preserves keys.
     *
     * Laravel falls back to `data` for a paginated response without a wrap key, so a caller passes `data` then.
     *
     * @param  class-string  $singular
     * @param  ReflectionClass<object>  $reflection  the class whose preserve-keys state applies
     */
    private function paginatedResourceType(string $singular, ReflectionClass $reflection, string $itemsKey): string
    {
        $name = TsNaming::resourceTypeName($singular);

        if ($itemsKey === 'data' && ! ResourceCollections::preservesKeys($reflection)) {
            return 'JsonResourcePaginator<'.$name.'>';
        }

        return "Omit<JsonResourcePaginator<{$name}>, 'data'> & { "
            .JsEmitter::validJsObjectKey($itemsKey).': '.ResourceCollections::listType($name, $reflection).' }';
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
     * Whether the payload argument resolves to one of the paginator types.
     */
    private function firstArgumentIsPaginator(?Arg $first, ExpressionEngine $engine): bool
    {
        if ($first === null) {
            return false;
        }

        $type = $engine->resolve($first->value)['type'];

        foreach (self::PAGINATOR_NAMES as $paginator) {
            if (str_starts_with($type, $paginator.'<')) {
                return true;
            }
        }

        return false;
    }
}
