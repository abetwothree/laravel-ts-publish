<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An abstract model giving a UUID to another column only, so its key stays an integer. */
abstract class AbstractUniqueIdsKeyModel extends Model
{
    use HasUuids;

    /** Only the `uuid` column gets a UUID. */
    public function uniqueIds(): array
    {
        return ['uuid'];
    }
}
