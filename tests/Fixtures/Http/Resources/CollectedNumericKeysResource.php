<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource holding collections keyed by numbers, which its filter never enters, beside an array it lists.
 *
 * @mixin Depot
 */
class CollectedNumericKeysResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($this->id === 0) {
            return ['kept' => collect(['6' => $this->name])];
        }

        return [
            'kept' => collect(['6' => $this->name]),
            'kept_int' => collect([42 => $this->name]),
            'listed' => ['6' => $this->name],
        ];
    }
}
