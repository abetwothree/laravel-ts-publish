<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class TupleShapeMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a PHP list whose declared shape is an object literal with numeric keys.
     *
     * @return array{tuple: array{0: array<string, int>, 1: list<int>}}
     */
    public function provide(Model $model): array
    {
        return ['tuple' => [[], []]];
    }
}
