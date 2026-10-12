<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Depot;

/**
 * A test-only resource that preserves keys, whose list drops a when() element and so keeps the keys of the rest, and
 * whose collect() keeps a missing when() value, which no filter removes.
 *
 * @mixin Depot
 */
final class KeptConditionalListResource extends JsonResource
{
    public $preserveKeys = true;

    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'items' => [$this->when($request->has('x'), 'x'), 'y'],
            'collected' => collect([[$this->when($request->has('x'), 'x')]]),
        ];
    }
}
