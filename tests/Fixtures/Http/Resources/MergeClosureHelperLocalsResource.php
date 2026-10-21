<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose spread helper, read inside a merge closure, builds its variable from its own locals.
 *
 * @mixin Tag
 */
class MergeClosureHelperLocalsResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->merge(function () {
                return $this->defaults();
            }),
        ];
    }

    /** @return array<string, mixed> */
    private function defaults(): array
    {
        $k = 'slug';
        $extra = ['note' => 'none', 'slug' => $this->slug];
        $data = $extra;
        $data['name'] = $this->name;
        unset($data[$k]);

        return $data;
    }
}
