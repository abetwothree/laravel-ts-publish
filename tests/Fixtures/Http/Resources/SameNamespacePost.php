<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `posts` table, placed beside the API resource that names it with no import. */
class SameNamespacePost extends Model
{
    protected $table = 'posts';
}
