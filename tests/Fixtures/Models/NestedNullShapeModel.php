<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\NestedNullShapeCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\MaybeNode;
use Illuminate\Database\Eloquent\Casts\AsCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * Nullable JSON columns whose declared shape holds a nested null: one on an `array` cast, one on a class cast, and a
 * list of a class whose override holds one.
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
            'related_users' => AsCollection::of(MaybeNode::class),
        ];
    }
}
