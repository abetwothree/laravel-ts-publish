<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NumberedComment;

/** A service whose untyped methods the engine cannot read, one handing back the query it is given, one another. */
class OpaqueQueries
{
    /**
     * A query of comments whose content casts to an integer, whatever query it is given.
     */
    public function comments($query)
    {
        return NumberedComment::query();
    }

    /**
     * The query it is given.
     */
    public function same($query)
    {
        return $query;
    }
}
