<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * A test-only resource whose toArray() #[TsCasts] imports a type named like the enum its key reads.
 *
 * @mixin User
 */
class MethodCastDisplacedOwnNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['role' => ['type' => 'RoleType | null', 'import' => '@js/types/role']])]
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role,
        ];
    }
}
