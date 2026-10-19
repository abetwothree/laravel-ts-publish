<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `$with` default is a list, so `parent::with()` returns no named keys.
 *
 * @mixin Tag
 */
class ParentListWithResource extends JsonResource
{
    /** @var array<int, string> */
    public $with = ['a', 'b'];

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /** @return array<int|string, mixed> */
    public function with($request): array
    {
        $data = parent::with($request);
        $data['extra'] = 'x';

        return $data;
    }
}
