<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose annotated local holds a `whenNull()` with a default the engine cannot type.
 *
 * @mixin Post
 */
class WhenNullDroppedDefaultResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var string|null $label */
        $label = $this->whenNull($this->title, $this->opaqueLabel());

        return ['label' => $label];
    }

    /** Deliberately untyped, so the default is an arm the engine cannot type. */
    public function opaqueLabel() // @phpstan-ignore missingType.return
    {
        return $this->resource->getAttribute('title');
    }
}
