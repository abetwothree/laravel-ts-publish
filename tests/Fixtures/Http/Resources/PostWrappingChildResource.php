<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Parents\PostWrappingResource;
use Illuminate\Http\Request;
use Workbench\Crm\Models\User as Post;

/** Inherits its parent's `$resource` `@var`, while this file imports another class as `Post`. */
final class PostWrappingChildResource extends PostWrappingResource
{
    /**
     * A column of the parent's `Post`, beside a key naming this file's own `Post`.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'crm_class' => Post::class,
        ];
    }
}
