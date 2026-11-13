<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\AppendedPriorityWrapPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Spreads its post's own array, through `parent::toArray()`, beside a key of its own.
 *
 * @mixin AppendedPriorityWrapPost
 */
class AppendedWrapSpreadResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...parent::toArray($request), 'x' => 1];
    }
}
