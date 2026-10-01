<?php

declare(strict_types=1);

namespace Workbench\App\Packages\Audit\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reached only through AuditTrail, which is itself published on demand.
 */
class AuditNote extends Model
{
    protected $fillable = ['audit_trail_id', 'body'];

    /**
     * The entry the note belongs to.
     *
     * @return BelongsTo<AuditTrail, $this>
     */
    public function trail(): BelongsTo
    {
        return $this->belongsTo(AuditTrail::class, 'audit_trail_id');
    }
}
