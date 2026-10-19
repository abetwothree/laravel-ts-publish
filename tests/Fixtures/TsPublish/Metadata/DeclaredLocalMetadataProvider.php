<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;

final class DeclaredLocalMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide metadata read from a local an inline `@var` declares.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        /** @var string $table */
        $table = $model->getTable();

        return ['table' => $table];
    }
}
