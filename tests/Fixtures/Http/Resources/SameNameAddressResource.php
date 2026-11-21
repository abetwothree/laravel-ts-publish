<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\User;

/**
 * A test-only resource over the workbench User. The Address model and AddressResource, which publishes as `Address`
 * too, share a name, so one array member can spell it once for the model and once for the resource.
 *
 * @mixin User
 */
class SameNameAddressResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $pair = ['address' => new AddressResource($this->primaryAddress), 'raw' => $this->primaryAddress];
        $outer = ['pair' => ['address' => new AddressResource($this->primaryAddress), 'raw' => $this->primaryAddress]];

        return [
            'data_pair' => ['data' => ['raw' => $this->primaryAddress, 'address' => new AddressResource($this->primaryAddress)]],
            'data_lists' => ['data' => ['raw' => $this->addresses, 'list' => AddressResource::collection($this->addresses)]],
            'data_pair_then_resource' => [
                'data' => ['raw' => $this->primaryAddress, 'address' => new AddressResource($this->primaryAddress)],
                'again' => new AddressResource($this->primaryAddress),
            ],
            'models_or_resource' => ['x' => $request->boolean('a') ? $this->addresses : new AddressResource($this->primaryAddress)],
            'model_or_list_then_resource' => [
                'm' => $request->boolean('a') ? $this->primaryAddress : $this->addresses,
                'r' => new AddressResource($this->primaryAddress),
            ],
            'data_pair_resource_first' => ['data' => ['address' => new AddressResource($this->primaryAddress), 'raw' => $this->primaryAddress]],
            'resource_or_models' => ['x' => $request->boolean('a') ? new AddressResource($this->primaryAddress) : $this->addresses],
            'pair_read_back' => ['p' => $pair['address'], 'q' => $pair['raw']],
            'pair_spread' => [...$pair, 'n' => 1],
            'nested_spread' => [...$outer, 'n' => 1],
            'nested_read' => ['p' => $outer['pair']],
        ];
    }
}
