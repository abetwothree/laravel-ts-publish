<?php

declare(strict_types=1);

namespace Workbench\App\Concerns;

/**
 * Vague `: array` helpers declared in a trait's own file, typed only by their literal bodies.
 *
 * Deliberately carries no `@return` docblocks: a shape there would be read before the body ever is.
 */
trait QuotesBands
{
    /** An instance helper. */
    public function bandQuote(): array
    {
        return ['band' => 'B', 'ceiling' => 50];
    }

    /** A static helper. */
    public static function staticBandQuote(): array
    {
        return ['band' => 'B', 'ceiling' => 50];
    }
}
