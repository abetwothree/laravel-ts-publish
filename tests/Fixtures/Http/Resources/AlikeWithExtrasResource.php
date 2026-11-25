<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;

/**
 * A test-only resource whose `with()` sends the `Address` model beside `AddressResource`, which print one name, after
 * the model alone (GH-76).
 *
 * @mixin User
 */
class AlikeWithExtrasResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['r' => new AddressResource($this->primaryAddress)];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        return ['meta' => [
            'a' => $this->primaryAddress,
            'both' => [new AddressResource($this->primaryAddress), $this->primaryAddress],
        ]];
    }
}
