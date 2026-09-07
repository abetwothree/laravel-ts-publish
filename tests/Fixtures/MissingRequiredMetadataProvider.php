<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class MissingRequiredMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata without every required documented value.
     *
     * @return array{table: string, exists: bool}
     */
    public function provide(Model $model): array
    {
        return [
            'table' => $model->getTable(),
        ];
    }
}
