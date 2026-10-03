<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Workbench\App\Packages\Audit\Models\AuditNote;

/**
 * A test-only model on the `audit_trails` table, reached only through a relation. Its `notes()` relation reads the
 * facility's name, which a blank instance does not have, so the package cannot inspect it.
 */
class UninspectableTrail extends Model
{
    protected $table = 'audit_trails';

    /**
     * The facility the entry was recorded for.
     *
     * @return BelongsTo<UninspectableTrailFacility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(UninspectableTrailFacility::class, 'facility_id');
    }

    /**
     * The notes filed under the facility's name.
     *
     * @return HasMany<AuditNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AuditNote::class, 'audit_trail_id')->where('body', $this->facility->name);
    }
}
