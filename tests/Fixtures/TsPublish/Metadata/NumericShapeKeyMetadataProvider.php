<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class NumericShapeKeyMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a payload whose `@return` shape names the numeric keys `42` and `7`, which PHP stores as ints.
     *
     * @return array{label: string, 42?: string, 7: string}
     */
    public function provide(Model $model): array
    {
        return ['label' => $model->getTable()];
    }
}
