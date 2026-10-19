<?php

declare(strict_types=1);

namespace Workbench\App\Services;

use Workbench\App\Concerns\QuotesBands;

/**
 * Gets both its quote helpers from a trait in another file.
 */
final class BandQuoteService
{
    use QuotesBands;
}
