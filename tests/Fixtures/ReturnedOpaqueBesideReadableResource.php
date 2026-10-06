<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource returning a variable it cannot read beside one it reads completely, which holds keys.
 *
 * @mixin Tag
 */
class ReturnedOpaqueBesideReadableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('a')) {
            $a = $this->opaque();
            $a['x'] = 1;

            return $a;
        }

        $b = ['id' => $this->id, 'name' => $this->name];
        $b['y'] = 2;

        return $b;
    }

    /** @return array<string, mixed> */
    protected function opaque(): array
    {
        return $this->resource->toArray();
    }
}
