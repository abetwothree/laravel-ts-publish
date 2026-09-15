<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

/** An enum whose native `?self` return keeps the receiver's own type and gains null. */
enum ReceiverProbeEnum: string
{
    case First = 'first';
    case Last = 'last';

    /** The next case, or null after the last. */
    public function next(): ?self
    {
        return $this === self::First ? self::Last : null;
    }
}
