<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsIntersect;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose intersections spell global types the DOM declares, in an object and a generic.
 *
 * @mixin Post
 */
#[TsIntersect('{ n: Node; d: Document; q: Request; e: HTMLElement }', '@/types/a')]
#[TsIntersect('Partial<Record<string, Element | Headers | AbortSignal | KeyboardEvent>>', '@/types/a')]
class IntersectDomGlobalsResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
