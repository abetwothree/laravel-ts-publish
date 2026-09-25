<?php

declare(strict_types=1);

namespace Workbench\App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

/**
 * Fixture: a trait-declared accessor whose body reads a column of the model that uses the trait.
 * The body lives in this file, but `$this` is the model, so the scope must carry the model class.
 */
trait DerivesReleaseVersion
{
    protected function traitVersion(): Attribute
    {
        return Attribute::get(fn (): array => ['major' => $this->major, 'label' => 'v'.$this->major]);
    }
}
