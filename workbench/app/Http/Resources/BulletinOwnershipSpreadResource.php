<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\BulletinDigest;

/**
 * Spreads a related BulletinOwnership's toArray(), whose appended `owner` accessor names `User`.
 * BulletinDigest declares no `owner` accessor, so no name lookup imports it.
 *
 * @mixin BulletinDigest
 */
final class BulletinOwnershipSpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->ownership->toArray(),
        ];
    }
}
