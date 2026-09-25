<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasDisplayTitles;

/** The other model a RosterSlot's assignee can be. */
class Squad extends Model
{
    use HasDisplayTitles;
}
