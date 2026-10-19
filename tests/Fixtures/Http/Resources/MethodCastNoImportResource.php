<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * A test-only resource whose toArray() #[TsCasts] has no import, so the enum import its text spells must stay.
 *
 * @mixin User
 */
class MethodCastNoImportResource extends JsonResource
{
    /** @return array<string, mixed> */
    #[TsCasts(['role' => 'RoleType | null'])]
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'role' => $this->role,
        ];
    }
}
