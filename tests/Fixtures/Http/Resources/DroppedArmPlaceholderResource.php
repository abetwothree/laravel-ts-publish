<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose `[]` and `null` arms sit beside values the engine cannot type: in a union, in a local an
 * inline `@var` types or names a class with no published file, in a parameter's join past its block, and in the join
 * of a local's branches.
 *
 * @mixin Post
 */
class DroppedArmPlaceholderResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $rows = $this->opaqueRows();

        /** @var Model|null $unpublished */
        $unpublished = $this->id > 0 ? $this->opaqueRows() : null;

        /** @var list<string>|null $declared */
        $declared = $this->id > 0 ? $this->opaqueRows() : null;

        return [
            'ternary' => $this->id > 0 ? $this->opaqueRows() : [],
            'coalesce' => $rows ?? [],
            'both' => match (true) {
                $this->id > 1 => $this->opaqueRows(), $this->id > 0 => null, default => []
            },
            'literal' => [],
            'listed' => $this->id > 0 ? $this->declaredRows() : [],
            'kept' => $this->id > 0 ? $this->title : null,
            'unpublished_tag' => $unpublished,
            'declared_tag' => $declared,
            'parameter' => $this->transform($this->opaqueRows(), function ($value) {
                if ($this->id > 1) {
                    $value = null;
                }

                return $value;
            }),
            'walked' => $this->when($this->id > 0, function () {
                $built = ['id' => $this->id];

                if ($this->id > 1) {
                    $built['rows'] = $this->opaqueRows();
                } else {
                    $built['rows'] = [];
                }

                return $built;
            }),
        ];
    }

    /** Declared only as an array. */
    public function declaredRows(): array
    {
        return $this->resource->getAttribute('tags')->all();
    }

    /** Deliberately untyped. */
    public function opaqueRows()
    {
        return $this->resource->getAttribute('tags');
    }
}
