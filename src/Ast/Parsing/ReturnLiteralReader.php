<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use AbeTwoThree\LaravelTsPublish\Ast\Concerns\InspectsAstNodes;
use AbeTwoThree\LaravelTsPublish\Cache\DependencyRecorder;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Scalar\String_;

/**
 * Reads the literal every return of a method spells: a string, as `broadcastAs()` or `getAuthIdentifierName()` names
 * one, or a list of strings, as a `uniqueIds()` override names its columns.
 *
 * Whole literal or nothing: a concatenation folded to its literal prefix ships a wrong Echo key.
 *
 * @internal
 */
final class ReturnLiteralReader
{
    use InspectsAstNodes;

    /**
     * The string literal every return of a method spells, or null for anything else.
     *
     * @param  class-string  $class
     */
    public function stringLiteral(string $class, string $method): ?string
    {
        $literal = $this->literal($class, $method);

        return is_string($literal) ? $literal : null;
    }

    /**
     * The list of string literals every return of a method spells, or null for anything else.
     *
     * @param  class-string  $class
     * @return list<string>|null
     */
    public function strings(string $class, string $method): ?array
    {
        $literal = $this->literal($class, $method);

        return is_array($literal) ? $literal : null;
    }

    /**
     * The one literal every return with a value spells; null where the method yields or has no such return, a return
     * spells anything else, or two returns differ.
     *
     * @param  class-string  $class
     * @return string|list<string>|null
     */
    private function literal(string $class, string $method): string|array|null
    {
        // MethodLocator memoizes its parse, which is what records the file, so a warm read would record nothing.
        DependencyRecorder::recordClass($class);
        $stmts = resolve(MethodLocator::class)->locate($class, $method)?->method->stmts ?? [];

        if ($this->yields($stmts)) {
            return null;
        }

        $literals = array_map($this->literalOf(...), $this->collectReturnExpressions($stmts));
        $first = $literals[0] ?? null;

        return array_all($literals, fn (string|array|null $literal): bool => $literal === $first) ? $first : null;
    }

    /**
     * A string literal, or a list literal of string literals with no key and no spread; null for anything else.
     *
     * @return string|list<string>|null
     */
    private function literalOf(Expr $expr): string|array|null
    {
        if ($expr instanceof String_) {
            return $expr->value;
        }

        if (! $expr instanceof Array_) {
            return null;
        }

        $items = [];

        foreach ($expr->items as $item) {
            if ($item->key !== null || $item->unpack || ! $item->value instanceof String_) {
                return null;
            }

            $items[] = $item->value->value;
        }

        return $items;
    }
}
