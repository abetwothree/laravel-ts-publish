<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use JsonSerializable;

/** An amount Laravel sends through jsonSerialize(), an object no Arrayable, built directly or by a factory. */
final class AmountValue implements JsonSerializable
{
    /**
     * Hold an amount.
     */
    public function __construct(private readonly int $amount) {}

    /**
     * Hold an amount the caller passes by name.
     */
    public static function of(int $amount): self
    {
        return self::opaque($amount);
    }

    /**
     * Convert the amount for serialization.
     *
     * @return array{amount: int}
     */
    public function jsonSerialize(): array
    {
        return ['amount' => $this->amount];
    }

    /**
     * An amount the analysis does not read through, so a factory's declared class types it.
     */
    private static function opaque(int $amount): mixed
    {
        return new self($amount);
    }
}
