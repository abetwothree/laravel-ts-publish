<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Collectors\ModelMetadataCollector;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Workbench\App\Models\Post;

final class SingleModelMetadataCollector extends ModelMetadataCollector
{
    /**
     * Collect exactly one model regardless of configuration.
     *
     * @return Collection<int, class-string<Model>>
     */
    public function collect(): Collection
    {
        return collect([Post::class]);
    }
}
