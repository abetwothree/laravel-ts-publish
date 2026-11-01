<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

final class SameNamedEnumTypesMetadataProvider implements ModelMetadataProvider
{
    private Status $status = Status::Draft;

    private CrmStatus $crmStatus = CrmStatus::Lead;

    /**
     * Provide two same-named enums as values, which publish as two same-named types.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'app' => $this->status,
            'crm' => $this->crmStatus,
        ];
    }
}
