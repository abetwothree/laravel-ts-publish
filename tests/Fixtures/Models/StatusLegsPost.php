<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Casts\StatusLegsCast;
use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `posts` table whose `content` casts to a list of value objects holding enums. */
class StatusLegsPost extends Model
{
    protected $table = 'posts';

    /**
     * The list cast on `content`.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['content' => StatusLegsCast::class];
    }
}
