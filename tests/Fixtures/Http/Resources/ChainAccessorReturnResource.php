<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * A test-only resource reading `Post::excerpt`, an accessor that returns `null` from its body, through `?->`, under a
 * `toArray()` `@return` key and a closure `@return` that leave `null` out.
 *
 * @mixin Comment
 */
final class ChainAccessorReturnResource extends JsonResource
{
    /**
     * @return array{chain_accessor: string, closure_accessor: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'chain_accessor' => $this->post?->excerpt,
            'closure_accessor' => collect([1])->map(/** @return string */ fn ($i) => $this->post?->excerpt)->all(),
            'bare_chain_accessor' => $this->post?->excerpt,
        ];
    }
}
