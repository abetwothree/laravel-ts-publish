<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class MismatchedModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata without a matching TypeScript type.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['other' => 'string'])]
    public function provide(Model $model): array
    {
        return [
            'table' => $model->getTable(),
        ];
    }
}
