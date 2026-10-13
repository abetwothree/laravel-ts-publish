<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class TypedParameterMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata that reads a typed scalar parameter beside the model.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model, int $limit = 10): array
    {
        return ['limit' => $limit, 'table' => $model->getTable()];
    }
}
