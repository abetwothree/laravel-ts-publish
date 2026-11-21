<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

final class SameNamedEnumUnionMetadataProvider implements ModelMetadataProvider
{
    public bool $crm = false;

    private Status $status = Status::Draft;

    private CrmStatus $crmStatus = CrmStatus::Lead;

    /**
     * Provide a union of two same-named enums, beside an object holding one of each in the other order.
     *
     * @return array<string, mixed>
     */
    public function provide(Model $model): array
    {
        return [
            'either' => $this->crm ? $this->crmStatus : $this->status,
            'pair' => ['crm' => $this->crmStatus, 'app' => $this->status],
            'last' => $this->status,
        ];
    }
}
