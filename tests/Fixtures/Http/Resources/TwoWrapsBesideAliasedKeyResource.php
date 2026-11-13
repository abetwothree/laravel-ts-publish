<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Wraps the app status or a role beside a key reading the CRM status, and the reverse, with no `@return` (GH-154).
 */
final class TwoWrapsBesideAliasedKeyResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'subject' => $request->query('subject') === 'status'
                ? EnumResource::make(Status::Published)
                : EnumResource::make(Role::Guest),
            'crm' => CrmStatus::Active,
            'crm_subject' => $request->query('subject') === 'status'
                ? EnumResource::make(CrmStatus::Lead)
                : EnumResource::make(Role::Guest),
            'app' => Status::Published,
        ];
    }
}
