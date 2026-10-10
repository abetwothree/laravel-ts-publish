<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Collectors;

use AbeTwoThree\LaravelTsPublish\Collectors\ResourcesCollector;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use RuntimeException;

/** A resources collector that fails, to end a run part-way through. */
final class ThrowingResourcesCollector extends ResourcesCollector
{
    /**
     * Fail instead of collecting.
     *
     * @return Collection<int, class-string<JsonResource>>
     */
    public function collect(): Collection
    {
        throw new RuntimeException('The resources collector failed.');
    }
}
