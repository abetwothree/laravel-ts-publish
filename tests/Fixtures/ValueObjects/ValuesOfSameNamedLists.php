<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Workbench\App\Enums\Status;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\Address;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * The values array_values(), values() and an array cast list of the app's and the CRM's `Status`, or of the `Address`
 * model and its resource, each beside a later token of that name (GH-76).
 */
final class ValuesOfSameNamedLists
{
    public ?Address $address = null;

    /** @var array<string, Status> */
    public array $appByName = [];

    /** @var array<string, CrmStatus> */
    public array $crmByName = [];

    public bool $crm = false;

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $filtered = collect([CrmStatus::Lead, Status::Draft])->filter();

        return [
            'values' => ['list' => array_values(array_filter([CrmStatus::Lead, Status::Draft])), 'one' => Status::Draft],
            'one_class' => ['list' => array_values(array_filter([Status::Draft])), 'one' => CrmStatus::Lead],
            'of_all' => ['list' => array_values(collect([CrmStatus::Lead, Status::Draft])->filter()->all()), 'one' => Status::Draft],
            'of_key_by' => [
                'list' => array_values(collect([CrmStatus::Lead, Status::Draft])->keyBy(fn ($status) => $status->name)->all()),
                'one' => Status::Draft,
            ],
            'collection_values' => ['list' => $filtered->values()->all(), 'one' => Status::Draft],
            'wraps' => [
                'list' => array_values(array_filter([EnumResource::make(CrmStatus::Lead), EnumResource::make(Status::Draft)])),
                'one' => EnumResource::make(Status::Draft),
            ],
            'models' => [
                'list' => array_values(array_filter([new AddressResource($this->address), $this->address])),
                'one' => $this->address,
            ],
            'either_values' => array_values($this->crm ? $this->crmByName : $this->appByName),
            'either_cast' => (array) ($this->crm ? $this->crmByName : $this->appByName),
        ];
    }
}
