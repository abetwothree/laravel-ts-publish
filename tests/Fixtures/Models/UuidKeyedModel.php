<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;

/** A concrete subclass of AbstractKeyedModel that changes its key to a UUID. */
class UuidKeyedModel extends AbstractKeyedModel
{
    use HasUuids;

    protected $table = 'products';
}
