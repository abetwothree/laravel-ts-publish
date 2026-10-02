<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose annotated local holds a `whenNull()` over a value that drops an arm it cannot type.
 *
 * @mixin Post
 */
class WhenNullDroppedArmResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var string|null $label */
        $label = $this->whenNull($this->title ?? cache('k'));

        return ['label' => $label];
    }
}
