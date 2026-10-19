<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An abstract model whose unique-id columns come from config, which only running the application can tell. */
abstract class AbstractComputedUniqueIdsModel extends Model
{
    use HasUuids;

    /** The configured columns get a UUID. */
    public function uniqueIds(): array
    {
        return config('ids.unique', ['uuid']);
    }
}
