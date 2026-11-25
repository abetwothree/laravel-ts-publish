<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only resource whose returns send the `Address` model or `AddressResource`, and the app's or the CRM's `User`,
 * under one key each (GH-76).
 *
 * @mixin User
 */
final class AddressKindsReturnsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('resource')) {
            return ['address' => new AddressResource($this->primaryAddress), 'user' => $this->resource];
        }

        return ['address' => $this->primaryAddress, 'user' => CrmUser::first()];
    }
}
