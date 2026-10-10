<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource returning its parent's whole array plus a key, through PHP's array union: the parent's keys
 * win, so the `id` written again keeps the parent's type.
 *
 * @mixin Post
 */
class PostParentUnionResource extends PostResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return parent::toArray($request) + ['extra' => 1, 'id' => 'shadowed'];
    }
}
