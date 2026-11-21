<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;

/**
 * A test-only resource whose returned variable holds `AddressResource` beside the `Address` model, the first key
 * overwritten by the model on one branch, then read back (GH-76).
 *
 * @mixin User
 */
final class AddressKindsOverwriteResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['resource' => new AddressResource($this->primaryAddress), 'model' => $this->primaryAddress];

        if ($request->boolean('model')) {
            $data['resource'] = $this->primaryAddress;
        }

        return ['data' => $data, 'resource' => $data['resource'], 'model' => $data['model']];
    }
}
