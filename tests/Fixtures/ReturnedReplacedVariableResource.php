<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable is assigned whole twice: the second assignment drops the first's keys.
 *
 * @mixin Tag
 */
class ReturnedReplacedVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = ['id' => $this->id];
        $data = ['name' => $this->name];

        return $data;
    }
}
