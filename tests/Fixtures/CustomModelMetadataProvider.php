<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class CustomModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide custom runtime metadata for a model.
     *
     * @return array{
     *     table: string,
     *     details: array{exists: bool},
     * }
     */
    #[TsCasts([
        'details' => ['type' => 'ModelMetadataDetails', 'import' => '@/types/model-metadata'],
    ])]
    public function provide(Model $model): array
    {
        return [
            'table' => $model->getTable(),
            'details' => ['exists' => $model->exists],
        ];
    }
}
