<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * Fixture: a readonly value object a resource wraps in place of a model, so only an inline `@var` on the local holding
 * it names its class.
 */
final readonly class CartTotals
{
    public function __construct(
        public float $subtotal,
        public bool $chargeable,
        public int $count,
        public bool $hasExtras = false,
    ) {}

    /** A note only a cart with extras carries. */
    public function note(): ?string
    {
        return $this->hasExtras ? 'extras' : null;
    }

    /**
     * A label with no declared return type, so only the caller's `@var` says what it holds.
     */
    public function label()
    {
        return $this->chargeable ? 'chargeable' : 'free';
    }
}
