<?php

declare(strict_types=1);

namespace Workbench\App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Review extends Model
{
    protected $table = 'reviews';

    /** Polymorphic parent (Venue or Artist, including their subclass-scoped review children) */
    public function reviewable(): MorphTo
    {
        return $this->morphTo();
    }
}
