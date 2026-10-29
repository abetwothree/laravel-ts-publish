<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Priority;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

final class SameNamedEnumNamesMetadataProvider implements ModelMetadataProvider
{
    private Status $status = Status::Draft;

    private CrmStatus $crmStatus = CrmStatus::Lead;

    private Priority $priority = Priority::Low;

    /**
     * Provide two same-named enums' case names, one listed, beside a third enum's.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'app_names' => collect([$this->status])->map(fn (Status $status) => $status->name)->all(),
            'crm_name' => $this->crmStatus->name,
            'priority_name' => $this->priority->name,
        ];
    }
}
