<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class TupleShapeMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide two PHP lists: one whose declared shape is an object literal with numeric keys, one whose shape is the
     * tuple its keys 0 and 1 in order declare.
     *
     * @return array{
     *     numeric_keys: array{1: list<int>, 0: array<string, int>},
     *     tuple: array{0: array<string, int>, 1: list<int>},
     * }
     */
    public function provide(Model $model): array
    {
        return ['numeric_keys' => [[], []], 'tuple' => [[], []]];
    }
}
