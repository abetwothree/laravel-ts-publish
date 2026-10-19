<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

/**
 * Test-only vague `: array` helpers that return a variable, for the body fallback.
 */
class ReturnedVariableLinesService
{
    /** Only a variable return: the body fallback types it from the variable's writes. */
    public static function lines(): array
    {
        $lines = ['unit' => '1.00'];
        $lines['tax'] = 0.2;

        return $lines;
    }

    /** A literal and a variable return, which the shape cannot read whole, so a value read types it. */
    public function mixedLines(bool $flag): array
    {
        if ($flag) {
            return ['unit' => '1.00'];
        }

        $lines = ['unit' => '2.00', 'tax' => 0.2];

        return $lines;
    }
}
