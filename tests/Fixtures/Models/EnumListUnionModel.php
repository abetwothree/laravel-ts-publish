<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\StatusListCast;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\StatusThenStatusesCast;
use Illuminate\Database\Eloquent\Model;

/**
 * Nullable JSON columns cast to one case of an enum before a list of it, and to a list of the enum alone.
 */
class EnumListUnionModel extends Model
{
    protected $table = 'property_docblock_fixtures';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'meta_info' => StatusThenStatusesCast::class,
            'tags' => StatusListCast::class,
        ];
    }
}
