<?php

declare(strict_types=1);

namespace Workbench\App\Services;

use Carbon\CarbonInterface;
use DateTimeImmutable;
use Exception;
use Illuminate\Support\Carbon;
use Workbench\App\ValueObjects\ShiftNote;

/**
 * Returns values whose JSON is not their `__toString()`: an Exception writes `{}`, a plain DateTimeImmutable its date
 * object, a ShiftNote its `?string`, and the CarbonInterface now() declares Carbon's ISO string.
 */
class ShiftClock
{
    /**
     * The last fault the clock raised.
     */
    public function lastFault(): Exception
    {
        return new Exception('jammed');
    }

    /**
     * When the shift started.
     */
    public function startedAt(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-01-01 08:00:00');
    }

    /**
     * The note handed to the next shift.
     */
    public function handoverNote(): ShiftNote
    {
        return new ShiftNote;
    }

    /**
     * When the next bell rings.
     */
    public function nextBell(): CarbonInterface
    {
        return Carbon::now()->addHour();
    }
}
