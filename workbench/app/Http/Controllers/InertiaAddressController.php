<?php

declare(strict_types=1);

namespace Workbench\App\Http\Controllers;

use Inertia\Inertia;
use Inertia\Response;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\Address;

/** Renders a resource whose exported TypeScript name differs from its class basename. */
class InertiaAddressController
{
    public function show(Address $address): Response
    {
        return Inertia::render('Addresses/Show', ['address' => AddressResource::make($address)]);
    }
}
