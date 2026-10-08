<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose returned variable starts from an array_merge() of literals.
 *
 * @mixin Tag
 */
class ReturnedMergeVariableResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $data = array_merge(['id' => $this->id], ['name' => $this->name]);
        $data['label'] = strtoupper($this->name);

        return $data;
    }
}
