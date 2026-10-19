<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

/**
 * The same numeric keys in a resource that preserves its keys, so every array keeps them.
 */
class DepotTierKeyedResource extends DepotTierResource
{
    public $preserveKeys = true;
}
