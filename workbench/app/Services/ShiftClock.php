<?php

declare(strict_types=1);

namespace Workbench\App\Services;

use Exception;
use Workbench\App\ValueObjects\ShiftNote;

/**
 * Returns values whose JSON is not their `__toString()`: an Exception writes `{}`, and a ShiftNote its `?string`.
 */
class ShiftClock
{
    public function lastFault(): Exception
    {
        return new Exception('jammed');
    }

    public function handoverNote(): ShiftNote
    {
        return new ShiftNote;
    }
}
