<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Address;

/**
 * A test-only resource with two return branches whose helpers cast `longitude`, which its model also casts, to one
 * type; only one of them marks it optional.
 *
 * @mixin Address
 */
class BranchCastsOptionalApartResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('a')) {
            return [...$this->flagged()];
        }

        return [...$this->plain()];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['longitude' => ['type' => 'BranchLongitude', 'optional' => true]])]
    protected function flagged(): array
    {
        return ['longitude' => $this->longitude];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['longitude' => 'BranchLongitude'])]
    protected function plain(): array
    {
        return ['longitude' => $this->longitude];
    }
}
