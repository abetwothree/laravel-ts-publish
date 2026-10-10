<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Warehouse;

/**
 * A test-only resource whose #[TsCasts], with no import, spells the name both models of its `only()` key share.
 *
 * @mixin Warehouse
 */
#[TsCasts(['last_user_activity_by_typed' => 'User | null'])]
class CastSpelledTwoClassAccessorOnlyResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only(['last_user_activity_by_typed']),
        ];
    }
}
