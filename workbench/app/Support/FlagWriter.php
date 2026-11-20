<?php

declare(strict_types=1);

namespace Workbench\App\Support;

/** Clears a flag handed to it by reference, for fixtures whose discriminant an object no scan names changes. */
final class FlagWriter
{
    /** Clears the flag. */
    public function clear(bool &$flag): bool
    {
        $flag = false;

        return true;
    }
}
