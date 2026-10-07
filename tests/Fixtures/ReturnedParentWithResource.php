<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource over a JsonResource parent whose `with()` builds a variable on `parent::with()` and whose
 * `jsonSerialize()` returns `parent::jsonSerialize()`: neither sends the model's keys.
 *
 * @mixin Tag
 */
class ReturnedParentWithResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }

    /** @return array<string, mixed> */
    public function with($request): array
    {
        $data = parent::with($request);
        $data['extra'] = 'x';

        return $data;
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return parent::jsonSerialize();
    }
}
