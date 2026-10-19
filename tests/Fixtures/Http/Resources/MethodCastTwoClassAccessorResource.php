<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose toArray() #[TsCasts] imports a type named like the two models its key reads.
 *
 * @mixin Warehouse
 */
class MethodCastTwoClassAccessorResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['last_user_activity_by_typed' => ['type' => 'User | null', 'import' => '@js/types/user']])]
    public function toArray(Request $request): array
    {
        return [
            'last_user_activity_by_typed' => $this->last_user_activity_by_typed,
        ];
    }
}
