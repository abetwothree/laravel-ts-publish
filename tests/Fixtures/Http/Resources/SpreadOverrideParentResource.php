<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/**
 * A test-only resource spreading its parent's helper through `parent::`.
 *
 * @mixin Post
 */
final class SpreadOverrideParentResource extends SpreadOverrideParentBaseResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [...parent::titled()];
    }
}
