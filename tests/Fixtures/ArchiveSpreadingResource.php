<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Packages\Audit\Models\AuditTrail;

/**
 * A test-only resource that spreads ArchiveReadingResource, so the engine analyzes that resource on its behalf.
 *
 * @mixin AuditTrail
 */
class ArchiveSpreadingResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...ArchiveReadingResource::make($this->resource)->resolve($request),
        ];
    }
}
