<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/** An abstract model keyed by HasUuids, whose getKeyType() answers `string`. */
abstract class AbstractUuidKeyModel extends Model
{
    use HasUuids;
}
