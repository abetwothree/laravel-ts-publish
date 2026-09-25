<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\BulletinOwnership;

/**
 * Spreads a related BulletinDigest's toArray(), whose appended accessors name `Comment`, `User` and `BulletinDigest`.
 * BulletinOwnership declares none of those accessors, so no name lookup imports them.
 *
 * @mixin BulletinOwnership
 */
final class BulletinDigestSpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->digest->toArray(),
        ];
    }
}
