<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Parsing;

use PhpParser\Node\Expr;
use PhpParser\Node\Expr\NullsafePropertyFetch;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Identifier;

/**
 * The variable, or chain of named property reads over one, that an expression reads, compared step by step rather than
 * by its printed text. Each segment's flag says whether it is read with `?->`.
 *
 * @internal
 */
final readonly class ReadPath
{
    /**
     * A path from its root variable through each named segment.
     *
     * @param  list<string>  $segments
     * @param  list<bool>  $nullsafe  one flag per segment
     */
    private function __construct(
        public string $root,
        public array $segments = [],
        public array $nullsafe = [],
    ) {}

    /**
     * The path an expression reads: a variable with a written name, or a `->`/`?->` chain of named reads over one.
     *
     * Null for anything else; a method call is never a path, since a second call may return another value.
     */
    public static function of(Expr $expr): ?self
    {
        if ($expr instanceof Variable) {
            return is_string($expr->name) ? new self($expr->name) : null;
        }

        if (! ($expr instanceof PropertyFetch || $expr instanceof NullsafePropertyFetch) || ! $expr->name instanceof Identifier) {
            return null;
        }

        return self::of($expr->var)?->with($expr->name->toString(), $expr instanceof NullsafePropertyFetch);
    }

    /**
     * Whether two expressions read the same path.
     */
    public static function sameRead(Expr $left, Expr $right): bool
    {
        $path = self::of($left);
        $other = self::of($right);

        return $path !== null && $other !== null && $path->same($other);
    }

    /**
     * Whether another path reads the same variable through the same segments, each with the same operator.
     */
    public function same(self $other): bool
    {
        return $this->root === $other->root && $this->segments === $other->segments && $this->nullsafe === $other->nullsafe;
    }

    /**
     * The one segment this path reads on a base path, or null when it is no direct member of it.
     */
    public function memberOf(self $base): ?string
    {
        return $this->parent()?->same($base) === true ? $this->segments[count($this->segments) - 1] : null;
    }

    /**
     * The path without its last segment, or null at its root.
     */
    public function parent(): ?self
    {
        return $this->segments === []
            ? null
            : new self($this->root, array_slice($this->segments, 0, -1), array_slice($this->nullsafe, 0, -1));
    }

    /**
     * This path read one segment further.
     */
    private function with(string $segment, bool $nullsafe): self
    {
        return new self($this->root, [...$this->segments, $segment], [...$this->nullsafe, $nullsafe]);
    }
}
