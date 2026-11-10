<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Role;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Post;

/**
 * Wraps a post's status or its author's role, else sends rows only the `@return` types with both enums bare (GH-154).
 *
 * @mixin Post
 */
class WrapsBesideFilledRowsResource extends JsonResource
{
    /** @return array{subject: Status|Role|list<array{status: Status, role: Role}>} */
    public function toArray(Request $request): array
    {
        return [
            'subject' => $request->query('subject') === 'status'
                ? EnumResource::make($this->status)
                : ($request->query('subject') === 'role'
                    ? EnumResource::make($this->author->role ?? Role::Guest)
                    : array_map(fn ($comment) => ['status' => cache('status'), 'role' => cache('role')], $this->comments->all())),
        ];
    }
}
