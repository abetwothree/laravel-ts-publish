<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class DefaultModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide the package's default runtime metadata for a model.
     *
     * @return array{morphClass: string}
     */
    public function provide(Model $model): array
    {
        return [
            'morphClass' => (string) $model->getMorphClass(),
        ];
    }
}
