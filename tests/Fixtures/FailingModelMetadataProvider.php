<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;
use Workbench\App\Models\User;

final class FailingModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata unless the model is intentionally unusable.
     *
     * @return array{table: string}
     */
    public function provide(Model $model): array
    {
        if ($model instanceof User) {
            throw new RuntimeException('Metadata is unavailable for this model.');
        }

        return ['table' => $model->getTable()];
    }
}
