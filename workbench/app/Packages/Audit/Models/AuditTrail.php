<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Workbench\App\Models\Facility;

/**
 * Lives outside every configured model directory, as a package's model does, so it is published only because
 * Facility relates to it.
 */
class AuditTrail extends Model
{
    protected $fillable = ['facility_id', 'action'];

    /**
     * The facility the entry was recorded for.
     *
     * @return BelongsTo<Facility, $this>
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Published on demand in turn: reached only through this model.
     *
     * @return HasMany<AuditNote, $this>
     */
    public function notes(): HasMany
    {
        return $this->hasMany(AuditNote::class);
    }

    /**
     * Left out: the related model has no table, so there is nothing to publish for it.
     *
     * @return HasOne<AuditArchive, $this>
     */
    public function archive(): HasOne
    {
        return $this->hasOne(AuditArchive::class);
    }
}
