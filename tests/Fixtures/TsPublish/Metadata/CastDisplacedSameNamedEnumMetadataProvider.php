<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TsPublish\Metadata;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

final class CastDisplacedSameNamedEnumMetadataProvider implements ModelMetadataProvider
{
    private Status $status = Status::Draft;

    private CrmStatus $crmStatus = CrmStatus::Lead;

    /**
     * Provide one enum's case name beside a same-named enum's, whose keys a cast and a docblock retype.
     *
     * @return array{b: string, c: string}
     */
    #[TsCasts(['b' => ['type' => 'CrmLabel', 'import' => '@/types/crm']])]
    public function provide(Model $model): array
    {
        return [
            'a' => $this->status->name,
            'b' => $this->crmStatus->name,
            'c' => $this->crmStatus->name,
        ];
    }
}
