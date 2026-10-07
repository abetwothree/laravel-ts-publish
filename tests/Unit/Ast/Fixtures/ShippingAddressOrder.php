<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Workbench\App\Models\Address;

/** An order whose `shippingAddress()` relation serializes under the key its `shipping_address` column writes. */
final class ShippingAddressOrder extends Model
{
    protected $table = 'orders';

    /** @return BelongsTo<Address, $this> */
    public function shippingAddress(): BelongsTo
    {
        return $this->belongsTo(Address::class, 'shipping_address_id');
    }
}
