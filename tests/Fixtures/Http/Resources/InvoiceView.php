<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

/** A plain class, not a model, placed beside the API resource that names it with no import. */
final class InvoiceView
{
    public string $number;

    public ?float $total;

    public bool $paid;
}
