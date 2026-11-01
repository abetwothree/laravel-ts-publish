<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose helper fills with `??=` a key its argument holds a `when()` in.
 *
 * @mixin Tag
 */
class ReturnedCoalesceSeedWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['h' => $this->filled(['id' => $this->id, 'w' => $this->when($request->boolean('c'), 1)])];
    }

    /**
     * Fill the key a `when()` may leave out.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function filled(array $data): array
    {
        $data['w'] ??= 'none';

        return $data;
    }
}
