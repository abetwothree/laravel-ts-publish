<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Nullable JSON columns whose `@property` tags spell a tuple, an object shape, a quoted key and a nested nullable
 * shape.
 *
 * @property list{int, string}|null $tags
 * @property object{a: int}|null $meta_info
 * @property array{'theme': string, size: int}|null $related_users
 * @property array{a: ?array{b: int}}|null $owner_snapshot
 */
class PropertyDocblockShapeModel extends Model
{
    protected $table = 'property_docblock_fixtures';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'meta_info' => 'object',
            'related_users' => 'array',
            'owner_snapshot' => 'array',
        ];
    }
}
