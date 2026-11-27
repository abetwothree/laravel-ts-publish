<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Ast\Arrays\Shape;

/**
 * A three-valued answer about an array: it holds on every path, on some, or on none.
 *
 * @internal
 */
enum Certainty
{
    case Yes;
    case Maybe;
    case No;

    /**
     * Whether the answer holds on every path.
     */
    public function yes(): bool
    {
        return $this === self::Yes;
    }

    /**
     * Whether the answer holds on some paths only.
     */
    public function maybe(): bool
    {
        return $this === self::Maybe;
    }

    /**
     * Whether the answer holds on no path.
     */
    public function no(): bool
    {
        return $this === self::No;
    }
}
