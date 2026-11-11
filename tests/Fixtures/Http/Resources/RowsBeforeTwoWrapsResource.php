<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;
use Workbench\Crm\Enums\Status as CrmStatus;

/**
 * Sends rows only the `@return` types, else wraps a status or a role, with two enums named `Status` bare (GH-154).
 *
 * @mixin Post
 */
final class RowsBeforeTwoWrapsResource extends JsonResource
{
    /** @return array{subject: Status|Role|list<array{c: CrmStatus, s: Status}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $request->query('subject') === 'rows'
                ? array_map(fn ($comment) => ['c' => cache('c'), 's' => cache('s')], $this->comments->all())
                : ($request->query('subject') === 'status'
                    ? EnumResource::make(Status::Published)
                    : EnumResource::make(Role::Guest)),
        ];
    }
}
