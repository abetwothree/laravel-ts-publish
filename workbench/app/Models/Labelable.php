<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Labelable extends MorphPivot
{
    protected $table = 'labelables';

    /** Polymorphic parent (Venue or Artist) the pivot row labels */
    public function labelable(): MorphTo
    {
        return $this->morphTo();
    }
}
