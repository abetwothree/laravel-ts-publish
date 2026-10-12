<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use Illuminate\Support\Carbon;

/**
 * A Carbon date whose own jsonSerialize() keeps Carbon's `mixed`; only its `@return` says it writes a string.
 */
class StampedDate extends Carbon
{
    /**
     * The date json_encode() writes.
     *
     * @return string
     */
    public function jsonSerialize(): mixed
    {
        return $this->format('Y-m-d');
    }
}
