<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Product;

/**
 * Replaces a model's `author` relation with a product keyed `p-1`.
 */
final class AuthorSwapper
{
    /**
     * Swap the relation on a model.
     */
    public static function swap(Model $model): void
    {
        $product = new Product;
        $product->forceFill(['id' => 'p-1']);
        $model->setRelation('author', $product);
    }
}
