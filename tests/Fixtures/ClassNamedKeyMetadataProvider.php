<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Metadata\Contracts\ModelMetadataProvider;
use Illuminate\Database\Eloquent\Model;
use Workbench\App\Enums\Status as AppStatus;
use Workbench\Crm\Enums\Status as CrmStatus;

final class ClassNamedKeyMetadataProvider implements ModelMetadataProvider
{
    /**
     * Provide a docblock-overridden key named after a built-in class beside a live key of the same TypeScript name.
     *
     * @return array{error: string}
     */
    public function provide(Model $model): array
    {
        return [
            'error' => $this->appStatus(),
            'state' => $this->crmStatus(),
        ];
    }

    /**
     * The stale channel: the docblock retypes this key, so its enum import must be dropped.
     */
    private function appStatus(): AppStatus
    {
        return AppStatus::Published;
    }

    /**
     * The live channel, which renders the same StatusType name from a different path.
     */
    private function crmStatus(): CrmStatus
    {
        return CrmStatus::Lead;
    }
}
