<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\TraitBodies;

use Illuminate\Http\Request;
use Workbench\App\Models\Post;

/** A toArray() whose inline `@var` names a class only this file imports. */
trait ReadsDeclaredPost
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        /** @var Post $post */
        $post = $this->resource->featuredPost();

        return ['title' => $post->title];
    }
}
