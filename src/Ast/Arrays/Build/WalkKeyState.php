<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Build;

use AbeTwoThree\LaravelTsPublish\Ast\Contracts\ExpressionHandler;
use AbeTwoThree\LaravelTsPublish\Ast\Values\ValueResult;

/**
 * What the paths of a returned variable's walk leave one key holding, which decides whether a `??=` of it runs: no
 * value, a plain value that may be null, or a closure or Inertia prop object, which may resolve to null.
 *
 * @phpstan-import-type HeldNulls from ExpressionHandler
 *
 * @internal
 */
final readonly class WalkKeyState
{
    /**
     * Hold what some path leaves the key holding.
     */
    public function __construct(
        public bool $unset = false,
        public bool $object = false,
        public bool $objectNull = false,
        public bool $plainNull = false,
    ) {}

    /**
     * What a path that never set the key leaves it holding.
     */
    public static function neverSet(): self
    {
        return new self(unset: true);
    }

    /**
     * What a key no write recorded holds, as its own type says.
     */
    public static function ofType(string $type): self
    {
        return new self(plainNull: ValueResult::mayBeNull($type));
    }

    /**
     * What a value leaves a key holding. A plain value's null `??=` replaces; an object on some arm resolves to the
     * null Inertia sends, beside the plain arms' (ValueResult::heldNullsOf()).
     *
     * @param  HeldNulls|null  $held
     */
    public static function ofValue(?array $held, string $type, bool $unset = false): self
    {
        return new self(
            $unset,
            $held !== null,
            $held['objectNull'] ?? false,
            $held === null ? ValueResult::mayBeNull($type) : $held['plainNull'],
        );
    }

    /**
     * What either of two sets of paths may leave the key holding.
     */
    public function either(self $other): self
    {
        return new self(
            $this->unset || $other->unset,
            $this->object || $other->object,
            $this->objectNull || $other->objectNull,
            $this->plainNull || $other->plainNull,
        );
    }

    /**
     * These paths, with one more that leaves the key with no value.
     */
    public function withUnset(): self
    {
        return new self(true, $this->object, $this->objectNull, $this->plainNull);
    }

    /**
     * These paths, past a write that sets the key on each that left it with no value, as a `+` lays the right's there.
     */
    public function withoutUnset(): self
    {
        return new self(false, $this->object, $this->objectNull, $this->plainNull);
    }
}
