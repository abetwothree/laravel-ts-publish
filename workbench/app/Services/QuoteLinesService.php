<?php

declare(strict_types=1);

namespace Workbench\App\Services;

/**
 * A vague `: array` signature over an array a local variable builds, so the body fallback reads the variable's writes.
 *
 * Deliberately carries no `@return` docblock: a shape there would be read before the body ever is.
 */
final class QuoteLinesService
{
    /** The base literal sets one key and a key write adds the other. */
    public function lines(): array
    {
        $lines = ['unit' => '1.00'];
        $lines['tax'] = 0.2;

        return $lines;
    }
}
