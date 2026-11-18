<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Workbench\Accounting\Models\Invoice;

/** A test-only invoice on the `invoices` table whose getters read a payment's `status_or_pending` accessor. */
class PaymentStatusInvoice extends Invoice
{
    protected $table = 'invoices';

    /** The latest payment's status, or Pending's value until it is paid. */
    protected function latestStatus(): Attribute
    {
        return Attribute::get(fn () => $this->latest_payment?->status_or_pending);
    }

    /** The first payment's status, or Pending's value until it is paid. */
    protected function firstStatus(): Attribute
    {
        return Attribute::get(fn () => $this->payments->first()?->status_or_pending);
    }
}
