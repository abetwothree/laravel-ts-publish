<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\EnumResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Team;
use Workbench\App\Models\User;

/**
 * A test-only resource whose map()s stay Collections, one row a wrap or a case, one a list of two wraps (GH-108 G18).
 *
 * @mixin Team
 */
final class KeptMapWrapsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'mixed' => $this->members->map(fn (User $member) => $member->id > 1 ? EnumResource::make($member->role) : Status::Published),
            'pair' => $this->members->map(fn (User $member) => [EnumResource::make($member->role), EnumResource::make(Status::Draft)]),
        ];
    }
}
