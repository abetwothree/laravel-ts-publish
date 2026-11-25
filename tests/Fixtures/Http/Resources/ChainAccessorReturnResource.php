<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Http\Resources\AddressResource;
use Workbench\App\Models\Address;
use Workbench\App\Models\Comment;
use Workbench\App\Support\Opaque;

/**
 * A test-only resource reading another method's body under outer declarations that leave `null` out: `Post::excerpt`,
 * an accessor that returns `null` from its body, through `?->` under a `toArray()` `@return` key and a closure
 * `@return`; a private helper's literal under an inline `@var`, over a nullable column or a ternary's `null`; and a
 * helper's `object` the declaration refines to the `Address` model, beside `AddressResource`, which prints that name too.
 *
 * @mixin Comment
 */
final class ChainAccessorReturnResource extends JsonResource
{
    /**
     * @return array{chain_accessor: string, closure_accessor: list<string>, same_named: array{resource: AddressResource, model: Address}}
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
            'same_named' => ['resource' => new AddressResource(Opaque::value(1)), 'model' => $this->anyObject()],
            'closure_same_named' => collect([1])->map(
                /** @return array{resource: mixed, model: Address} */
                fn ($i) => ['resource' => new AddressResource(Opaque::value($i)), 'model' => $this->anyObject()],
            )->all(),
        ];
    }

    /**
     * An object whose declaration names no class.
     */
    private function anyObject(): object
    {
        return Opaque::value(1);
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
