<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\StatusedCrmRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Sends a subject as its wrapped status or as mapped rows whose cache read the `@return` docblock types as the app
 * user, beside the CRM author the body types (GH-154).
 *
 * @property-read StatusedCrmRows $resource
 */
final class StatusOrRowsResource extends JsonResource
{
    /** @return array{subject: Status|list<array{by: CrmUser, other: User|null}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->kind === 'status'
                ? EnumResource::make($this->resource->status)
                : array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
