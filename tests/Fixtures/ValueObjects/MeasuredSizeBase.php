<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

/** A test-only payload whose `toArray()` reads an untyped size first through a helper that returns it. */
class MeasuredSizeBase
{
    protected $size = 'none';

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'h' => $this->measure(),
            'size' => $this->size,
        ];
    }

    /** The size, through a helper that declares no return type. */
    public function measure()
    {
        return $this->size;
    }
}
