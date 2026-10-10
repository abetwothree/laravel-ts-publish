<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * A test-only resource building a variable from its parent's whole array plus a key, returned beside a literal: the
 * walk reads the union whole, so only the keys both returns set stay required.
 *
 * @mixin Post
 */
class PostParentUnionVariableResource extends PostResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        if ($request->boolean('brief')) {
            return ['id' => $this->id];
        }

        $data = parent::toArray($request) + ['extra' => 1];

        return $data;
    }
}
