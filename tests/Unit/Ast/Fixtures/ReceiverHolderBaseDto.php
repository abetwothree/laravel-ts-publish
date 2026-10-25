<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** A parent whose body builds `new static` and reads a property ReceiverHolderChildDto redeclares, and a private one. */
class ReceiverHolderBaseDto
{
    /** @var ReceiverBaseDto */
    protected $held;

    private ReceiverBaseDto $own;

    /** Fill both properties as this class declares them. */
    public function __construct()
    {
        $this->held = new ReceiverBaseDto;
        $this->own = new ReceiverBaseDto;
    }

    /** A `new static`, built in this body. */
    public function fresh(): static
    {
        return new static;
    }

    /** The class `$this->held` holds, read in this body. */
    public function heldClass(): string
    {
        return $this->held::class;
    }

    /** The class `$this->own` holds, read in this body. */
    public function ownClass(): string
    {
        return $this->own::class;
    }
}
