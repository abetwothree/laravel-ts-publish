<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource that adds a local a spread helper fills, whose own body reads a local of the same name.
 *
 * @mixin Tag
 */
class ReadLocalHelperLocalsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $defaults = $this->defaults();

        return ['id' => $this->id] + $defaults;
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        $defaults = ['note' => 'none'];
        $data = $defaults;
        $data['x'] = 1;

        return $data;
    }
}
