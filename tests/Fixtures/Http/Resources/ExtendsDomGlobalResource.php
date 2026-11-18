<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose extends clauses only build on global types the DOM declares.
 *
 * @mixin Post
 */
#[TsExtends('Partial<Response>', '@/types/a')]
#[TsExtends('Pick<Event, "type">', '@/types/a')]
class ExtendsDomGlobalResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
