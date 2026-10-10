<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class MissingTableModel extends Model
{
    protected $table = 'table_that_was_never_migrated';
}
