<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `with()` returns a literal on one path and a variable built on `parent::with()` on the
 * other, so each path's keys are optional.
 *
 * @mixin Tag
 */
class ParentWithBranchesResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        if ($request->boolean('lite')) {
            return ['lite' => true];
        }

        $data = parent::with($request);
        $data['extra'] = 'x';

        return $data;
    }
}
