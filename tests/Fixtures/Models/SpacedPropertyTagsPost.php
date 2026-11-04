<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * JSON columns whose `@property` tags spell a quoted literal holding a space and a generic spaced before its `<`.
 *
 * @property 'not set'|array<string, int>|null $meta_info
 * @property array <string, int>|null $related_users
 */
class SpacedPropertyTagsPost extends Model
{
    protected $table = 'property_docblock_fixtures';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['meta_info' => 'json', 'related_users' => 'json'];
    }
}
