<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A test-only model whose table no migration creates, so a test can create it between two runs. */
class LateTableModel extends Model
{
    protected $table = 'late_tables';
}
