<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `toArray()` spreads its own `jsonSerialize()`, which runs `toArray()` again: Laravel never
 * returns from it, and the analysis must still end.
 *
 * @mixin Tag
 */
class JsonSerializeLoopResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, ...$this->jsonSerialize()];
    }

    /** @return array<string, mixed> */
    public function jsonSerialize(): array
    {
        return parent::jsonSerialize();
    }
}
