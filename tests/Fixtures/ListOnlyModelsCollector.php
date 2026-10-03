<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Models\Facility;

/** A project's own models collector: it lists its classes, extends no collector, and cannot say what it accepts. */
final class ListOnlyModelsCollector
{
    /**
     * List exactly one model regardless of configuration.
     *
     * @return Collection<int, class-string<Model>>
     */
    public function collect(): Collection
    {
        return collect([Facility::class]);
    }
}
