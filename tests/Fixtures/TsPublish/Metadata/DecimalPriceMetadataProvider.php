<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Product;

final class DecimalPriceMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide the price of a product priced 9.99, which its `decimal:2` cast reads as a string.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        $product = new Product(['price' => 9.99]);

        return ['price' => $product->price];
    }
}
