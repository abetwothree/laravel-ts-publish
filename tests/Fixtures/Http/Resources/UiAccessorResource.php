<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\UiBoard;

/**
 * Reads one accessor under a key of another name, so only the read brings the accessor class's override import.
 *
 * @mixin UiBoard
 */
class UiAccessorResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['renamed' => $this->gauge_attr];
    }
}
