<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;

/**
 * A test-only resource reading another method's body under outer declarations that leave `null` out: `Post::excerpt`,
 * an accessor that returns `null` from its body, through `?->` under a `toArray()` `@return` key and a closure
 * `@return`; and a private helper's literal under an inline `@var`, over a nullable column or a ternary's `null`.
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
        /** @var array{parent: int} $columnHelper */
        $columnHelper = $this->parentColumn();
        /** @var array{parent: int} $ternaryHelper */
        $ternaryHelper = $this->parentTernary();

        return [
            'chain_accessor' => $this->post?->excerpt,
            'closure_accessor' => collect([1])->map(/** @return string */ fn ($i) => $this->post?->excerpt)->all(),
            'bare_chain_accessor' => $this->post?->excerpt,
            'var_column_helper' => $columnHelper,
            'var_ternary_helper' => $ternaryHelper,
            'bare_column_helper' => $this->parentColumn(),
        ];
    }

    /**
     * The comment's parent key, a nullable column.
     */
    private function parentColumn(): array
    {
        return ['parent' => $this->parent_id];
    }

    /**
     * The comment's own id beside a ternary's `null`.
     */
    private function parentTernary(): array
    {
        return ['parent' => $this->id > 1 ? $this->id : null];
    }
}
