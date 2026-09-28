<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Workbench\App\Models\Concerns\HasDisplayTitles;

/** One model a RosterSlot's assignee can be. */
class Crew extends Model
{
    use HasDisplayTitles;
}
