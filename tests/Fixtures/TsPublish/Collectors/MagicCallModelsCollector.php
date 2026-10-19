<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Collectors;

use BadMethodCallException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Models\Facility;

/** A project's own models collector that serves collect() through __call, as a decorator forwarding calls does. */
final class MagicCallModelsCollector
{
    /**
     * Answer a collect() call with one model, and refuse every other name.
     *
     * @param  list<mixed>  $arguments
     * @return Collection<int, class-string<Model>>
     */
    public function __call(string $name, array $arguments): Collection
    {
        return $name === 'collect'
            ? collect([Facility::class])
            : throw new BadMethodCallException(sprintf('Method [%s] does not exist.', $name));
    }
}
