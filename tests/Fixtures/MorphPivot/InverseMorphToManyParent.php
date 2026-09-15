<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\MorphPivot;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphToMany;
use Workbench\App\Models\Label;
use Workbench\App\Models\Labelable;

/**
 * Reuses the already-migrated `artists` table purely so resolveContext()'s schema lookup succeeds;
 * declares the *inverse* (morphedByMany) side of a real custom pivot, to isolate getInverse() from
 * the base-Pivot exclusion.
 */
final class InverseMorphToManyParent extends Model
{
    protected $table = 'artists';

    public function labels(): MorphToMany
    {
        return $this->morphedByMany(Label::class, 'labelable')->using(Labelable::class);
    }
}
