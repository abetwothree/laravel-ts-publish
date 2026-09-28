<?php

declare(strict_types=1);

namespace Workbench\App\Models\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Model;

/**
 * @template TChild of Model
 */
trait AggregatesChildren
{
    /** @return Attribute<EloquentCollection<int, TChild>, never> */
    protected function childItems(): Attribute
    {
        return Attribute::get(fn (): EloquentCollection => new EloquentCollection);
    }
}
