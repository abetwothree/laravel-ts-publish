<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/** Provides a key a cast spells a wrap's const in, beside a wrap under a key the payload leaves out (GH-160). */
final class WrapOutsidePayloadMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide the cast key and the wrap under `hidden`.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['shown' => '{ w: AsEnum<typeof Status> }'])]
    public function provide(Model $model): array
    {
        return ['shown' => ['w' => $model->getKey()], 'hidden' => EnumResource::make(Status::Draft)];
    }
}
