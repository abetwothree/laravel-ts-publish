<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\ResourceCollection;

/**
 * The #[UseResourceCollection] target for Registrar. Deliberately declares no $collects and has
 * no matching RegistrarGroupResource/RegistrarGroup class, so its element type is undeterminable:
 * its items list as unknown, and Registrar's toResourceCollection() never falls through to RegistrarResource.
 */
class RegistrarGroupCollection extends ResourceCollection
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'data' => $this->collection,
        ];
    }
}
