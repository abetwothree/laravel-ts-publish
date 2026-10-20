<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** An invoice on the `invoices` table whose methods a resource's own, or a wrapped class's, namesakes shadow. */
class NamesakeInvoice extends Model
{
    protected $table = 'invoices';

    protected $guarded = [];

    /** A static kind code. */
    public static function kind(): int
    {
        return 1;
    }

    /** The total, formatted. */
    public function total(): string
    {
        return number_format((float) $this->total, 2);
    }

    /** The invoice number. */
    public function reference(): string
    {
        return $this->number;
    }

    /** A status code. */
    public function status(): int
    {
        return 1;
    }

    /** Whether this is the first invoice. */
    public function isFirst(): bool
    {
        return true;
    }

    /** A label the resource's own untypable namesake shadows. */
    public function opaque(): string
    {
        return 'model';
    }

    /** A label the resource's own untyped, untypable namesake shadows. */
    public function untypedOpaque(): string
    {
        return 'model';
    }

    /** A literal shape behind a vague array. */
    public function summary(): array
    {
        return ['a' => 1];
    }

    /** A flag the resource's own branchy namesake shadows. */
    public function branchy(): bool
    {
        return true;
    }
}
