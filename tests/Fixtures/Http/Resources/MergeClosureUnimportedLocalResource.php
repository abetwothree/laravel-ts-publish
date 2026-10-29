<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose merge closure adds with `+` a method local it neither imports nor writes, which Laravel
 * throws on as an undefined variable.
 *
 * @mixin Tag
 */
class MergeClosureUnimportedLocalResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $extra = ['note' => 'none'];

        return [
            'id' => $this->id,
            $this->merge(function () {
                return ['name' => $this->name] + $extra;
            }),
        ];
    }
}
