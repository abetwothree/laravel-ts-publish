<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Declares no toArray() over a model whose relation and column share one serialized key.
 *
 * @mixin ShippingAddressOrder
 */
final class ShippingAddressOrderResource extends JsonResource {}
