<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Handlers;

use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\ChecksPreserveKeys;
use AbeTwoThree\LaravelTsPublish\Analyzers\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\AnalysisScope;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionEngine;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\ValueResult;
use AbeTwoThree\LaravelTsPublish\Facades\LaravelTsPublish;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Arg;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Expr\StaticCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Name;
use ReflectionClass;

/**
 * A resource handed straight to `Inertia::render()` is serialized whole, so its prop type is the
 * collection's own JSON shape, not the element array a nested resource key flattens to. What the
 * resource wraps decides the rest: a paginator adds the pagination members, a plain collection does not.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class InertiaResourcePropHandler implements ExpressionHandler
{
    use ChecksPreserveKeys;
    use InspectsAstNodes;

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
                'type' => LaravelTsPublish::resourceTypeName($className),
                'optional' => $optional,
                'resourceFqcn' => $className,
            ];
        }

        // A flat collection ($wrap = null) IS the paginator's data, so it becomes the paginator
        // itself over its singular resource; a wrapping one keeps its own type beside the members.
        $defaults = $reflection->getDefaultProperties();

        if (! array_key_exists('wrap', $defaults) || $defaults['wrap'] !== null) {
            return [
                ...ValueResult::unknown(),
                'type' => LaravelTsPublish::resourceTypeName($className).' & ResourcePagination',
                'optional' => $optional,
                'resourceFqcn' => $className,
                'customImports' => ['@tolki/types' => ['ResourcePagination']],
            ];
        }

        $singular = $this->resolveCollectedResourceClass($className);

        if ($singular === null) {
            return null;
        }

        return [
            ...$this->paginatedResourceType($singular, $reflection),
            'optional' => $optional,
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

        if ($this->firstArgumentIsPaginator($this->resourcePayloadArguments($expr, $className)->at(0), $engine)) {
            return [
                ...$this->paginatedResourceType($className, $reflection),
                'optional' => $optional,
            ];
        }

        return [
            ...ValueResult::unknown(),
            'type' => 'AnonymousResourceCollection<'.LaravelTsPublish::resourceTypeName($className).'>',
            'optional' => $optional,
            'resourceFqcn' => $className,
            'customImports' => ['@tolki/types' => ['AnonymousResourceCollection']],
        ];
    }

    /**
     * The paginator type over a singular resource, keyed when the reflected class preserves keys.
     *
     * @param  class-string  $singular
     * @param  ReflectionClass<object>  $reflection  the class whose preserve-keys state applies
     * @return ValueExpressionResult
     */
    private function paginatedResourceType(string $singular, ReflectionClass $reflection): array
    {
        $name = LaravelTsPublish::resourceTypeName($singular);

        $type = $this->collectionPreservesKeys($reflection)
            ? "Omit<JsonResourcePaginator<{$name}>, 'data'> & { data: Record<string, {$name}> }"
            : 'JsonResourcePaginator<'.$name.'>';

        return [
            ...ValueResult::unknown(),
            'type' => $type,
            'resourceFqcn' => $singular,
            'customImports' => ['@tolki/types' => ['JsonResourcePaginator']],
        ];
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
