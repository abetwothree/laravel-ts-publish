<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * A JSON column whose `@phpstan-property` lists its strings beside a vaguer `@property`.
 *
 * @property array|null $tags
 *
 * @phpstan-property list<string> $tags
 */
class PhpstanPropertyPost extends Model
{
    protected $table = 'property_docblock_fixtures';

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['tags' => 'array'];
    }
}
