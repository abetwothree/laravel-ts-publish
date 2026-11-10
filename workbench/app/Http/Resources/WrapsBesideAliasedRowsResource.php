<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Wraps a status or a role beside rows only the `@return` types with two enums named `Status` bare (GH-154).
 *
 * @mixin Post
 */
class WrapsBesideAliasedRowsResource extends JsonResource
{
    /**
     * @return array{
     *     subject: Status|Role|list<array{crm: CrmStatus, status: Status}>,
     *     crm_subject: CrmStatus|Role|list<array{status: Status, crm: CrmStatus}>
     * }
     */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $request->query('subject') === 'status'
                ? EnumResource::make($this->status)
                : ($request->query('subject') === 'role'
                    ? EnumResource::make($this->author->role ?? Role::Guest)
                    : array_map(fn ($comment) => ['crm' => cache('crm'), 'status' => cache('status')], $this->comments->all())),
            'crm_subject' => $request->query('subject') === 'status'
                ? EnumResource::make(CrmStatus::Lead)
                : ($request->query('subject') === 'role'
                    ? EnumResource::make($this->author->role ?? Role::Guest)
                    : array_map(fn ($comment) => ['status' => cache('status'), 'crm' => cache('crm')], $this->comments->all())),
        ];
    }
}
