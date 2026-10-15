<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\CollectsLocalVarBindings;
use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use AbeTwoThree\LaravelTsPublish\Cache\PublishedClasses;
use AbeTwoThree\LaravelTsPublish\Facades\TsNaming;
use Illuminate\Http\Resources\Json\ResourceCollection;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\Assign;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;
use PhpParser\Node\Stmt;
use ReflectionClass;

/**
 * Whether a resource collection's toArray() sends its collected list, the list ResourceCollection::toArray() returns:
 * the response wraps that list as it wraps a body-less collection's, and a resource key holding the collection sends
 * it bare.
 *
 * @phpstan-import-type ValueExpressionResult from ExpressionHandler
 *
 * @internal
 */
final class ResourceCollectionBody
{
    use CollectsLocalVarBindings;
    use InspectsAstNodes;

    /**
     * Whether an expression a collection's toArray() returns, read in the class declaring that body, is the whole
     * collected list: `parent::toArray()` over a parent that sends it, `$this->collection` or `$this->resource`, as is
     * or through `toArray()`, `jsonSerialize()` or `all()`, or one of those spread alone into a literal.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $declaring
     */
    public function returnsCollectedList(Expr $returned, ReflectionClass $declaring): bool
    {
        if ($this->isParentCallTo($returned, 'toArray')) {
            $parent = $declaring->getParentClass();

            return $parent !== false && $this->sendsCollectedList($parent);
        }

        if ($returned instanceof Array_) {
            return count($returned->items) === 1
                && $returned->items[0]->unpack
                && $this->returnsCollectedList($returned->items[0]->value, $declaring);
        }

        if ($returned instanceof MethodCall
            && $returned->name instanceof Identifier
            && in_array($returned->name->toLowerString(), ['toarray', 'jsonserialize', 'all'], true)
            && ! $returned->isFirstClassCallable()
            && $returned->getArgs() === []
        ) {
            $returned = $returned->var;
        }

        return $this->isResourceFetch($returned)
            || ($returned instanceof PropertyFetch
                && $this->isThisPropertyFetch($returned)
                && $returned->name instanceof Identifier
                && $returned->name->toString() === 'collection');
    }

    /**
     * Whether a collection's toArray(), wherever it is declared, sends its collected list on every path: Laravel's own,
     * or a body each of whose returns is that list or a variable holding it.
     *
     * @template T of object
     *
     * @param  ReflectionClass<T>  $collection
     */
    public function sendsCollectedList(ReflectionClass $collection): bool
    {
        if (ResourceCollections::runsFrameworkToArray($collection)) {
            return true;
        }

        if (! $collection->isSubclassOf(ResourceCollection::class)) {
            return false;
        }

        $stmts = resolve(MethodLocator::class)->locate($collection->getName(), 'toArray')?->method->stmts ?? [];
        $returns = $this->collectReturnExpressions($stmts);
        $declaring = $collection->getMethod('toArray')->getDeclaringClass();

        return $returns !== [] && array_all(
            $returns,
            fn (Expr $returned): bool => $this->returnsCollectedList($returned, $declaring)
                || ($returned instanceof Variable
                    && is_string($returned->name)
                    && $this->holdsCollectedList($stmts, $returned->name, $declaring)),
        );
    }

    /**
     * A collection Laravel builds in a resource key, as the value it sends there: its own type while the run publishes
     * it and its toArray() sends other keys, else the list it collects; null when it collects no element.
     *
     * @param  class-string<ResourceCollection>  $collection
     * @return ValueExpressionResult|null
     */
    public function nestedValue(string $collection): ?array
    {
        // The nesting class's type follows this body, and a memoized method lookup records nothing on a later build.
        DependencyRecorder::recordClass($collection);

        $reflection = new ReflectionClass($collection);

        if (PublishedClasses::exports($collection) && ! $this->sendsCollectedList($reflection)) {
            return [...ValueResult::unknown(), 'type' => TsNaming::resourceTypeName($collection), 'resourceFqcn' => $collection];
        }

        $element = ResourceCollections::collectedElement($reflection);

        return $element === null ? null : ResourceCollections::listValue($element, ResourceCollections::preservesKeys($reflection));
    }

    /**
     * Whether a returned variable holds the collected list: the body assigns it that list once and writes it nowhere
     * else.
     *
     * @param  array<Stmt>  $stmts
     * @param  ReflectionClass<object>  $declaring
     */
    private function holdsCollectedList(array $stmts, string $name, ReflectionClass $declaring): bool
    {
        $writes = array_values(array_filter(
            $this->collectVariableWrites($stmts, $declaring),
            fn (array $write): bool => $write[0] === $name,
        ));

        return count($writes) === 1
            && $writes[0][1] instanceof Assign
            && $writes[0][1]->var instanceof Variable
            && $this->returnsCollectedList($writes[0][1]->expr, $declaring);
    }
}
