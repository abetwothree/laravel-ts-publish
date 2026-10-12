<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

use DateTime;
use JsonSerializable;

/**
 * A reading json_encode() writes as PHP's date object: jsonSerialize() keeps `mixed`, and its `@return` says DateTime.
 */
class ShiftClockReading implements JsonSerializable
{
    /**
     * The date json_encode() writes.
     *
     * @return DateTime
     */
    public function jsonSerialize(): mixed
    {
        return new DateTime('2026-01-01 00:00:00');
    }
}
