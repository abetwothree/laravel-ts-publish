<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/** Provides an enum resource under a key of an inline array, whose `AsEnum<typeof Status>` the companion spells. */
final class NestedEnumWrapMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide the wrap under `probe.w`.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return ['probe' => ['w' => EnumResource::make(Status::Draft)]];
    }
}
