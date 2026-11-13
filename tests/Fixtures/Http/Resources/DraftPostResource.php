<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A resource the naming convention backs with DraftPost, whose IDE-helper mixin names no class.
 *
 * @mixin IdeHelperDraftPost
 */
final class DraftPostResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title];
    }
}
