<?php

declare(strict_types=1);

namespace Workbench\App\ValueObjects;

/**
 * A rate with one public, one protected and one private property: json_encode() writes only the public one, so
 * FreightClass::rateCard() publishes `{amount: …}` with neither hidden property.
 */
final class RateCard
{
    protected string $currency = 'USD';

    private string $rateKey = 'internal-rate-key';

    public function __construct(
        public int $amount,
    ) {}
}
