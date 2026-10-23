<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is replaced whole in one branch.
 *
 * @mixin Tag
 */
class ReturnedBranchReplacedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id, 'name' => $this->name];

        if ($request->boolean('slim')) {
            $data = ['slug' => $this->slug];
        }

        return $data;
    }
}
