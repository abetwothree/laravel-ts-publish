<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * A test-only resource whose class-level #[TsCasts] imports a type named like the enum its key reads.
 *
 * @mixin User
 */
#[TsCasts(['role' => ['type' => 'RoleType | null', 'import' => '@js/types/role']])]
class CastDisplacedOwnNameResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'role' => $this->role,
        ];
    }
}
