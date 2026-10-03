<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExclude;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use LogicException;
use Workbench\App\Packages\Audit\Models\AuditNote;

/**
 * A test-only model on the `audit_trails` table, reached only through a relation. `notes()` and the excluded
 * `facilityNotes()` read the facility's name, which a blank instance does not have; `trailNotes()` declares no return
 * type, so it is found by its body; `summary()` is no relation, so nothing calls it.
 */
class UnreadableRelationTrail extends Model
{
    protected $table = 'audit_trails';

    /**
     * The facility the entry was recorded for.
     *
     * @return BelongsTo<UnreadableRelationFacility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(UnreadableRelationFacility::class, 'facility_id');
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

    /**
     * The facility's notes, which no generated file publishes.
     *
     * @return HasMany<AuditNote, $this>
     */
    #[TsExclude]
    public function facilityNotes(): HasMany
    {
        return $this->hasMany(AuditNote::class, 'audit_trail_id')->where('body', $this->facility->name);
    }

    /**
     * Every note filed against the entry.
     *
     * @return HasMany<AuditNote, $this>
     */
    public function trailNotes()
    {
        return $this->hasMany(AuditNote::class, 'audit_trail_id');
    }

    /**
     * Not a relation, so reading the model's relations must never call it.
     */
    public function summary(): string
    {
        throw new LogicException('Only a relation read should reach a relation method.');
    }
}
