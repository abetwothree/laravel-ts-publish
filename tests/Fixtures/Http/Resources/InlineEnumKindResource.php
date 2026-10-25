<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource whose nested array reads its enum's case name, the only token of the enum it spells.
 *
 * @mixin Post
 */
class InlineEnumKindResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'meta' => ['name' => $this->status->name],
        ];
    }
}
