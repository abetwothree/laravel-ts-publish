<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/**
 * A test-only model on the `posts` table whose metadata shape spells model names as string literals.
 *
 * @property array{kind: 'Post'|'User', label: string} $metadata
 */
class LiteralKindPost extends Model
{
    protected $table = 'posts';

    /**
     * The metadata column's cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }
}
