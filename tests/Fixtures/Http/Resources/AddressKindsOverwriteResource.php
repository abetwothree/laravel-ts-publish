<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A test-only resource whose returned variable holds `AddressResource` beside the `Address` model, and the app's
 * `User`, the first key overwritten by the model and the user by the CRM's on one branch, then read back (GH-76).
 *
 * @mixin User
 */
final class AddressKindsOverwriteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = [
            'resource' => new AddressResource($this->primaryAddress),
            'model' => $this->primaryAddress,
            'user' => $this->resource,
        ];

        if ($request->boolean('model')) {
            $data['resource'] = $this->primaryAddress;
            $data['user'] = CrmUser::first();
        }

        return ['data' => $data, 'resource' => $data['resource'], 'model' => $data['model'], 'user' => $data['user']];
    }
}
