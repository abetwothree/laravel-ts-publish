<?php

declare(strict_types=1);

namespace Workbench\App\Support;

/** Hides a value's type from the engine, for a fixture that needs a side it cannot type. */
final class Opaque
{
    /** Returns its argument behind a declared mixed. */
    public static function value(mixed $value): mixed
    {
        // A body read follows `return $value;` to the argument the call passes, which would type it.
        return func_get_arg(0);
    }
}
