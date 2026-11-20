<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Attributes\TsExtends;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose clauses build on one name from two paths, one also spelling it as a string literal.
 *
 * @mixin Post
 */
#[TsExtends('Pick<Meta, "Meta">', '@/types/a')]
#[TsExtends('Partial<Meta>', '@/types/b')]
class AliasBesideLiteralResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['id' => $this->id];
    }
}
