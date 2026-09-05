<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class OptionalModelMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata with a value that may be omitted.
     *
     * @return array{table: string, exists?: bool}
     */
    #[TsCasts(['exists' => ['type' => 'ExistsFlag', 'import' => '@/types/exists-flag']])]
    public function provide(Model $model): array
    {
        $metadata = [
            'table' => $model->getTable(),
        ];

        if ($model->getTable() === 'users') {
            $metadata['exists'] = $model->exists;
        }

        return $metadata;
    }
}
