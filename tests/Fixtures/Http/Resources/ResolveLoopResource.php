<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Tag;

/**
 * A test-only resource whose `resolve()` spreads `parent::resolve()`, which runs `toArray()`, which spreads
 * `jsonSerialize()`, which runs `resolve()` again: Laravel never returns from it, and the analysis must still end.
 *
 * @mixin Tag
 */
class ResolveLoopResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id, ...$this->jsonSerialize()];
    }

    /** @return array<string, mixed> */
    public function resolve($request = null): array
    {
        return [...parent::resolve($request), 'resolved' => true];
    }
}
