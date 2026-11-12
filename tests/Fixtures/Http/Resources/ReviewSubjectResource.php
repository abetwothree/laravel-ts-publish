<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\ValueObjects\ReviewRows;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * Sends a subject as its wrapped status or as mapped rows, under a `@return` that types the status bare and lists the
 * rows' members in another order than the body (GH-154).
 *
 * @property-read ReviewRows $resource
 */
final class ReviewSubjectResource extends JsonResource
{
    /** @return array{subject: Status|list<array{other: User|null, by: CrmUser}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $this->resource->kind === 'status'
                ? EnumResource::make($this->resource->status)
                : array_map(fn ($r) => ['by' => $r->author, 'other' => cache('other')], $this->resource->rows),
        ];
    }
}
