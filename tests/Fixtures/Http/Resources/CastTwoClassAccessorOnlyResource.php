<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose class-level #[TsCasts] imports a type named like the two models its `only()`
 * key reads.
 *
 * @mixin Warehouse
 */
#[TsCasts(['last_user_activity_by_typed' => ['type' => 'User | null', 'import' => '@js/types/user']])]
class CastTwoClassAccessorOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['last_user_activity_by_typed']),
        ];
    }
}
