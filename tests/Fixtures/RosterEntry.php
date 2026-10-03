<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;

/** A test-only model on the `roster_entries` view, which a test creates over `facilities`. */
class RosterEntry extends Model
{
    protected $table = 'roster_entries';
}
