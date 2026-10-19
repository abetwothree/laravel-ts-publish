<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NestedNullShapeCast;
use Illuminate\Database\Eloquent\Model;

/**
 * Nullable JSON columns whose declared shape holds a nested null: one on an `array` cast, one on a class cast.
 *
 * @property array{a: ?int} $owner_snapshot
 */
class NestedNullShapeModel extends Model
{
    protected $table = 'property_docblock_fixtures';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'owner_snapshot' => 'array',
            'meta_info' => NestedNullShapeCast::class,
        ];
    }
}
