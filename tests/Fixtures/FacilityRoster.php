<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A test-only model on the `facilities` table, related to a model that sits on a database view. */
class FacilityRoster extends Model
{
    protected $table = 'facilities';

    /**
     * The roster rows the view lists for the facility.
     *
     * @return HasMany<RosterEntry, $this>
     */
    public function entries(): HasMany
    {
        return $this->hasMany(RosterEntry::class, 'id');
    }
}
