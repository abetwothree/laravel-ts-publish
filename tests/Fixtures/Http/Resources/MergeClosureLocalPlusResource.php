<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose merge closures add a local with `+`: one the closure imports, and one of its own that
 * shadows an outer local of the same name.
 *
 * @mixin Tag
 */
class MergeClosureLocalPlusResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $extra = ['note' => 'none'];
        $own = ['shadowed' => 'outer'];

        return [
            'id' => $this->id,
            $this->merge(function () use ($extra) {
                return ['name' => $this->name] + $extra;
            }),
            $this->merge(function () {
                $own = ['flag' => true];

                return ['slug' => $this->slug] + $own;
            }),
        ];
    }
}
