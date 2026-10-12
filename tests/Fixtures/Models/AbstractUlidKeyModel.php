<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** An abstract model keyed by HasUlids, whose getKeyType() answers `string`. */
abstract class AbstractUlidKeyModel extends Model
{
    use HasUlids;
}
