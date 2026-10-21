<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose merge closure returns, alone, a variable with a key write the walk cannot name.
 *
 * @mixin Tag
 */
class ReturnedMergeClosureVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            $this->mergeWhen($this->id > 0, function () use ($request) {
                $merged = ['x21_a' => $this->id];
                $merged[$request->string('key')->toString()] = 1;

                return $merged;
            }),
        ];
    }
}
