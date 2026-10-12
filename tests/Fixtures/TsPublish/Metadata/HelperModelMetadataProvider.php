<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class HelperModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata built by helpers that read the model they are passed.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['naming' => $this->naming($model), ...$this->keying($model)];
    }

    /**
     * The model's table, nested.
     *
     * @return array<string, mixed>
     */
    private function naming(Model $model): array
    {
        return ['table' => $model->getTable()];
    }

    /**
     * The model's key name, spread.
     *
     * @return array<string, mixed>
     */
    private function keying(Model $model): array
    {
        return ['keyName' => $model->getKeyName()];
    }
}
