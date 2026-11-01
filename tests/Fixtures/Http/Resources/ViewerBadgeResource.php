<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * A test-only resource sending a user's id, null for a guest, with factories building it by self::make() and
 * static::make().
 */
class ViewerBadgeResource extends JsonResource
{
    /** Builds the badge through self::make(). */
    public static function forViewer(?User $user)
    {
        return self::make($user);
    }

    /** Builds the badge through static::make(). */
    public static function forViewerStatic(?User $user)
    {
        return static::make($user);
    }

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->resource?->id];
    }
}
