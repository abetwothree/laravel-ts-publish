<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is re-assigned as a spread of itself, the later key winning.
 *
 * @mixin Tag
 */
class ReturnedSelfSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'name' => $this->name];
        $data = [...$data, 'name' => strlen($this->name)];

        return $data;
    }
}
