<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class InvalidMetadataPayloadProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata with an invalid numeric property key.
     *
     * @return array<int, string>
     */
    public function provide(Model $model): array
    {
        return ['users'];
    }
}
