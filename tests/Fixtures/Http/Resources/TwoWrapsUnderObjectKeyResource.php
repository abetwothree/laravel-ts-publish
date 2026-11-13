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
 * Nests a wrapped status or role, else rows, under an object key after the CRM status read bare (GH-154).
 *
 * @mixin Post
 */
final class TwoWrapsUnderObjectKeyResource extends JsonResource
{
    /** @return array{row: array{first: CrmStatus, subject: Status|Role|list<array{c: CrmStatus, s: Status}>}} */
    public function toArray(Request $request): array
    {
        return [
            'row' => [
                'first' => CrmStatus::Active,
                'subject' => $request->query('subject') === 'status'
                    ? EnumResource::make(Status::Published)
                    : ($request->query('subject') === 'role'
                        ? EnumResource::make(Role::Guest)
                        : array_map(fn ($comment) => ['c' => cache('c'), 's' => cache('s')], $this->comments->all())),
            ],
        ];
    }
}
