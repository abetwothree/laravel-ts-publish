<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use DateTimeImmutable;
use ReturnTypeWillChange;

/** A date whose format() override drops PHP's tentative `string` return and documents its own. */
final class DocumentedFormatDate extends DateTimeImmutable
{
    /**
     * Format the date as a number.
     *
     * @return int
     */
    #[ReturnTypeWillChange]
    public function format($format)
    {
        return (int) parent::format($format);
    }
}
