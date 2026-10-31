<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable fills with `??=` a key holding a `when()`, past a branch that replaces
 * the variable with a value the walk cannot read.
 *
 * @mixin Tag
 */
class ReturnedCoalesceReplacedWhenResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'w' => $this->when($request->boolean('c'), 1)];

        if ($request->boolean('d')) {
            $data = json_decode('{"id":5}', true);
        }

        $data['w'] ??= 'none';

        return $data;
    }
}
