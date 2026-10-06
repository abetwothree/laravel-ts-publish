<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable spreads itself beside a `return []` guard, re-setting its one key.
 *
 * @mixin Tag
 */
class ReturnedSelfSpreadGuardResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('a')) {
            return [];
        }

        $data = ['id' => $this->id];
        $data = [...$data, 'id' => $this->name];

        return $data;
    }
}
