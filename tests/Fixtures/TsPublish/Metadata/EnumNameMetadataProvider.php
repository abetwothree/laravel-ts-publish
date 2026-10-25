<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;

final class EnumNameMetadataProvider implements ModelMetadataProvider
{
    private Status $status = Status::Draft;

    /**
     * Provide one backed enum's value and its case name.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'status' => $this->status->value,
            'status_name' => $this->status->name,
        ];
    }
}
