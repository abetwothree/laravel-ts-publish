<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A Team read through a subclass that knows one more relation — the narrowing target.
 *
 * `subscriber()` is deliberately a third relation on `owner_id`, distinct from Team's own `owner()`
 * and `map()`: TeamSubscriberResource asserts that narrowing reaches a relation only the subclass knows.
 */
class SubscribedTeam extends Team
{
    protected $table = 'teams';

    /** The user subscribed to this team */
    public function subscriber(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_id');
    }
}
