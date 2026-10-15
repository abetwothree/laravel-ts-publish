<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An abstract model giving UUIDs to its key and another column, so its key is a string. */
abstract class AbstractUniqueKeyListedModel extends Model
{
    use HasUuids;

    /** The key and the `uuid` column get a UUID. */
    public function uniqueIds(): array
    {
        return ['id', 'uuid'];
    }
}
