<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use Illuminate\Database\Eloquent\Model;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Identifier;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * The `only`/`except` filter vocabulary, the key list read off such a call's arguments, and whether a class runs
 * Model's own filter.
 *
 * The single home for all three: the filter-aware code (FiltersModelAttributes, RelationFilterHandler and
 * ReceiverMethodReturnResolver) reads keys and overrides here, and the generic reflectors ask it which calls to
 * decline. Stateless — no host state is read.
 *
 * @internal
 */
trait FiltersAttributeKeys
{
    /**
     * The attribute filter methods supported by the analyzer.
     *
     * @return list<string>
     */
    protected function supportedAttributeFilters(): array
    {
        return ['only', 'except'];
    }

    /**
     * Extract string keys from a filter method call's arguments.
     *
     * Supports both the array form `->only(['id', 'name'])` and the variadic form `->only('id', 'name')`; the
     * target is the receiver's own only()/except(), whose sole parameter names the array form.
     *
     * @return list<string>|null
     */
    protected function extractFilterKeys(MethodCall|NullsafeMethodCall $call, ReflectionMethod $target): ?array
    {
        $args = CallArguments::for($call, $target);
        $first = $args->at(0)?->value;

        if ($first === null) {
            return null; // @codeCoverageIgnore
        }

        // Array form: ->only(['id', 'name'])
        if ($first instanceof Array_) {
            /** @var list<string> $keys */
            $keys = [];

            foreach ($first->items as $arrayItem) {
                if ($arrayItem->value instanceof String_) {
                    $keys[] = $arrayItem->value->value;
                }
            }

            return $keys !== [] ? $keys : null;
        }

        // Variadic form: ->only('id', 'name') — read by func_get_args(), so positions only. PHP rejects a second
        // named argument here, and a lone `only(attributes: 'id')` is exactly what at(0) already returned.
        /** @var list<string> $keys */
        $keys = [];
        $position = 0;

        while (($arg = $args->at($position++)) !== null) {
            if ($arg->value instanceof String_) {
                $keys[] = $arg->value->value;
            }
        }

        return $keys !== [] ? $keys : null;
    }

    /**
     * Whether a call is an `only()`/`except()` attribute filter, whatever its key list.
     *
     * The generic reflectors ask this to decline: `Model::except()`'s `@return array` reflects to a list, and a
     * many-relation's filter keeps models by primary key. RelationFilterHandler and the receiver rules own them.
     */
    protected function callsAttributeFilter(MethodCall|NullsafeMethodCall $call): bool
    {
        return ! $call->isFirstClassCallable()
            && $call->name instanceof Identifier
            && in_array($call->name->toString(), $this->supportedAttributeFilters(), true);
    }

    /**
     * Whether a class runs Model's own `only()`/`except()`, whose return the filter answers describe.
     *
     * An override declares its own return, which PHP holds every subclass to, so reflection answers it instead.
     *
     * @param  class-string  $class
     */
    protected function runsModelFilter(string $class, string $methodName): bool
    {
        return method_exists($class, $methodName)
            && new ReflectionMethod($class, $methodName)->getDeclaringClass()->getName() === Model::class;
    }
}
