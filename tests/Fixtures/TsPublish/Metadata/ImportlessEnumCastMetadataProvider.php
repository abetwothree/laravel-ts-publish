<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

/** Provides an enum under an import-less cast that spells its type, beside an inferred number. */
final class ImportlessEnumCastMetadataProvider implements ModelMetadataProvider
{
    private Status $status = Status::Draft;

    /**
     * The metadata every model's companion carries.
     *
     * @return array<string, mixed>
     */
    #[TsCasts(['status' => 'StatusType | null'])]
    public function provide(Model $model): array
    {
        return ['status' => $this->status, 'other' => 1];
    }
}
