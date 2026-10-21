<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose spread helper unsets the key its parameter holds, then writes the parameter once.
 *
 * @mixin Tag
 */
class ReturnedHelperParameterKeyUnsetResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->trimmed('name')];
    }

    /** @return array<string, mixed> */
    private function trimmed(string $k): array
    {
        $data = ['id' => $this->id, 'name' => $this->name, 'slug' => $this->slug];
        unset($data[$k]);
        $k = 'slug';

        return $data;
    }
}
