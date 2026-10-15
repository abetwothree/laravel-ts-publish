<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsResourceCalls;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ResourceCollections;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\JsEmitter;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use AbeTwoThree\LaravelTsPublish\Support\ResourceWrapping;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;

/**
 * A resource handed straight to `Inertia::render()` is serialized whole, so its prop type is the collection's own JSON
 * shape, not the element array a nested resource key flattens to. What the resource wraps decides the rest: a paginator
 * adds the pagination members, a plain collection does not, and the live wrap key places the items.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class InertiaResourcePropHandler implements ExpressionHandler
{
    use InspectsAstNodes;
    use InspectsResourceCalls;

    /** @var list<string> The `@tolki/types` names ModelFinderHandler renders a paginator as. */
    private const PAGINATOR_NAMES = ['LengthAwarePaginator', 'SimplePaginator', 'CursorPaginator'];

    /** @return list<class-string<Expr>> */
    public function nodeClasses(): array
    {
        return [StaticCall::class, New_::class];
    }

    /** @return ValueExpressionResult|null */
    public function resolve(Expr $expr, AnalysisScope $scope, ExpressionEngine $engine): ?array
    {
        if ($expr instanceof New_) {
            return $this->resolveNewCollection($expr, $engine);
        }

        if ($expr instanceof StaticCall) {
            return $this->resolveStaticCollection($expr, $engine);
        }

        return null;
    }

    /**
     * `new SomeCollection($paginatorOrCollection)` — the collection resource types itself.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveNewCollection(New_ $expr, ExpressionEngine $engine): ?array
    {
        if (! $expr->class instanceof Name) {
            return null;
        }

        $className = $expr->class->toString();

        if (! is_a($className, ResourceCollection::class, true) || $this->isEnumResourceClass($className)) {
            return null;
        }

        $reflection = self::reflect($className);
        $optional = $this->hasConditionalNewArgument($expr, $className);

        if (! $this->firstArgumentIsPaginator($this->resourcePayloadArguments($expr, $className)->at(0), $engine)) {
            return [
                ...ValueResult::unknown(),
                'type' => TsNaming::resourceTypeName($className),
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        $wrapKey = ResourceWrapping::key($className);

        // A body-less collection without a wrap key IS the paginator's data, so it becomes the paginator itself.
        if ($wrapKey === null && ResourceCollections::runsFrameworkToArray($reflection)) {
            $singular = ResourceCollections::collectedResource($className);

            return $singular === null ? null : [
                ...ValueResult::unknown(),
                'type' => $this->paginatedResourceType($singular, $reflection, 'data'),
                'optional' => $optional,
                'resourceFqcn' => $singular,
                'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
            ];
        }

        // Without a wrap key, Laravel nests a body under `data` beside the pagination members.
        $name = TsNaming::resourceTypeName($className);

        return [
            ...ValueResult::unknown(),
            'type' => ($wrapKey === null ? '{ data: '.$name.' }' : $name).' & ResourcePagination',
            'optional' => $optional,
            'resourceFqcn' => $className,
            'customImports' => ['@tolki/types' => ['ResourcePagination']],
        ];
    }

    /**
     * `SomeResource::collection($paginatorOrCollection)` — an anonymous collection of that resource.
     *
     * @return ValueExpressionResult|null
     */
    private function resolveStaticCollection(StaticCall $expr, ExpressionEngine $engine): ?array
    {
        $className = $this->resolveStaticCallClassName($expr);

        if ($className === null
            || ! $expr->name instanceof Identifier
            || $expr->name->toString() !== 'collection'
            || $expr->isFirstClassCallable()
            || ! $this->isResourceClass($className)
            || $this->isEnumResourceClass($className)
            || is_a($className, ResourceCollection::class, true)) {
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

        $items = ResourceCollections::listType($name, $reflection);

        return [
            ...ValueResult::unknown(),
            'type' => $wrapKey === null ? $items : '{ '.JsEmitter::validJsObjectKey($wrapKey).': '.$items.' }',
            'optional' => $optional,
            'resourceFqcn' => $className,
        ];
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
