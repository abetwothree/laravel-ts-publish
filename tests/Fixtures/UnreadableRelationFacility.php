<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A test-only model on the `facilities` table that relates to a model with a relation that cannot be read. */
class UnreadableRelationFacility extends Model
{
    protected $table = 'facilities';

    /**
     * The trail entries recorded for the facility.
     *
     * @return HasMany<UnreadableRelationTrail, $this>
     */
    public function trails(): HasMany
    {
        return $this->hasMany(UnreadableRelationTrail::class, 'facility_id');
    }
}
