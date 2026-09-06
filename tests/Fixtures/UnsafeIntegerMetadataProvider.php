<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class UnsafeIntegerMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide an integer JavaScript cannot represent exactly.
     *
     * @return array{snowflake: int}
     */
    public function provide(Model $model): array
    {
        return ['snowflake' => PHP_INT_MAX];
    }
}
