<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Models\Concerns\HasDisplayTitles;

/**
 * Fixture: one morphTo read through two generics. `assignable` names only Model and no model declares the inverse;
 * `assignee` names the models the column can hold.
 */
class RosterSlot extends Model
{
    use HasDisplayTitles;

    /** @return MorphTo<Model, $this> */
    public function assignable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return MorphTo<Crew|Squad, $this> */
    public function assignee(): MorphTo
    {
        return $this->morphTo('assignee', 'assignable_type', 'assignable_id');
    }
}
