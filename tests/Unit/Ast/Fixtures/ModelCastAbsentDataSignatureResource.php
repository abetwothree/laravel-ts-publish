<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * The model's `#[TsCasts]` types `metadata`, a `${string}data` key this resource never publishes.
 *
 * @mixin Post
 */
final class ModelCastAbsentDataSignatureResource extends JsonResource
{
    /** Spreads the filled signature alone. */
    public function toArray(Request $request): array
    {
        return [...$this->docData()];
    }

    /**
     * `data`-suffixed keys only the docblock types.
     *
     * @return array<string, string>
     */
    public function docData(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}data"] = $this->opaque();
        }

        return $data;
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->resource->getAttribute('title');
    }
}
