<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

abstract class BaseInheritedMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata from a base class so the subclass inherits the body.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'enabled' => true,
            'table' => $model->getTable(),
        ];
    }
}
