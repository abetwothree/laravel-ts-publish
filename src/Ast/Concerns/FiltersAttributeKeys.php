<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Concerns;

use AbeTwoThree\LaravelTsPublish\Ast\CallArguments;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\NullsafeMethodCall;
use PhpParser\Node\Scalar\String_;
use ReflectionMethod;

/**
 * The `only`/`except` filter vocabulary and the key list read off such a call's arguments.
 *
 * The single home for both: FiltersModelAttributes composes this trait for `$this->only([...])`,
 * RelationFilterHandler for `$this->relation->only([...])`. Stateless — no host state is read.
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
}
