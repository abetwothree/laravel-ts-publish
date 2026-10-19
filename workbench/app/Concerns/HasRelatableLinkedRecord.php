<?php

declare(strict_types=1);

namespace Workbench\App\Concerns;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Workbench\App\Models\User as CauserModel;

/**
 * Fixture: a morphTo declared in a trait, not on the consuming model. The generic is aliased (`User as CauserModel`)
 * so this only resolves through the trait file's own use-map: Activity shares User's namespace, so an unaliased name
 * would be silently rescued by the plain "same namespace" fallback and prove nothing.
 */
trait HasRelatableLinkedRecord
{
    /** @return MorphTo<CauserModel, $this> */
    public function causer(): MorphTo
    {
        return $this->morphTo();
    }
}
