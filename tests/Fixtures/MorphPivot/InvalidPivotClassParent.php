<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MorphPivot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Workbench\App\Models\Label;

/**
 * Reuses the already-migrated `venues` table purely so resolveContext()'s schema lookup succeeds;
 * the relation itself is what's under test, not this model's own columns.
 */
final class InvalidPivotClassParent extends Model
{
    protected $table = 'venues';

    public function labels(): MorphToMany
    {
        return $this->morphToMany(Label::class, 'labelable')->using(NotAModelPivot::class);
    }
}
