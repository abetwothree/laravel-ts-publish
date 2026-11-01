<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose helpers build arrays that read themselves: a key from a key no reading types, and a
 * literal replacing one after a key the walk cannot name.
 *
 * @mixin Tag
 */
class ReturnedSelfReadHelperResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['meta' => $this->meta($request), 'rep' => $this->rep($request)];
    }

    /**
     * A key written from another key the array holds, one no reading types.
     *
     * @return array<string, mixed>
     */
    private function meta(Request $request): array
    {
        $m = ['id' => $this->id, 'q' => $request->input('q')];
        $m['v'] = $m['q'] ?? 'none';

        return $m;
    }

    /**
     * A literal replacing the array after a key the walk cannot name, reading the array it replaces.
     *
     * @return array<string, mixed>
     */
    private function rep(Request $request): array
    {
        $r = ['id' => $this->id];
        $r[$request->string('k', 'z')->toString()] = 1;
        $r = ['x' => $r['id'] ?? 0, 'y' => $this->name];

        return $r;
    }
}
