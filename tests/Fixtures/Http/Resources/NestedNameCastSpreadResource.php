<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\NestedNameSpreadHostPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A test-only resource spreading a related model whose column cast, with an import, only builds on a name.
 *
 * @mixin NestedNameSpreadHostPost
 */
final class NestedNameCastSpreadResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...$this->castPost->toArray()];
    }
}
