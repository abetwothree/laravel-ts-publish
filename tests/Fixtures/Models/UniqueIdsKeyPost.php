<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

/** A concrete post whose `uuid` column alone gets a UUID, so its key stays an integer. */
class UniqueIdsKeyPost extends AbstractUniqueIdsKeyModel
{
    protected $table = 'posts';
}
