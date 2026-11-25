<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Analyzers\Inertia\Fixtures;

use Illuminate\Http\Request;
use Inertia\Middleware;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\Address;

/**
 * Shares the `Address` model beside `AddressResource`, which publishes as `Address` too, the resource first (GH-76).
 */
final class MiddlewareWithAddressKinds extends Middleware
{
    public function share(Request $request): array
    {
        $address = Address::findOrFail($request->integer('address'));

        return [
            'pair' => ['address' => new AddressResource($address), 'raw' => $address],
            'either' => $request->boolean('wrap') ? new AddressResource($address) : $address,
        ];
    }
}
