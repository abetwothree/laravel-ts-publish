<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class BoundModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata whose types come only from the bound `$model` parameter's Laravel docblocks.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'table' => $model->getTable(),
            'keyName' => $model->getKeyName(),
            'routeKeyName' => $model->getRouteKeyName(),
            'morphClass' => $model->getMorphClass(),
        ];
    }
}
