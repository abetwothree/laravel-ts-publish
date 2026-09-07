<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class ModelMetadataProviderModel extends Model implements ModelMetadataProvider
{
    /**
     * Provide metadata for the provider-model watcher fixture.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['class' => 'string'])]
    public function provide(Model $model): array
    {
        return [
            'class' => $model::class,
        ];
    }
}
