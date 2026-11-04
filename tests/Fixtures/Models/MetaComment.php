<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `meta_comments` table its test creates: a nullable and a required `'array'` cast column. */
class MetaComment extends Model
{
    protected $table = 'meta_comments';

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['meta' => 'array', 'tags' => 'array'];
    }
}
