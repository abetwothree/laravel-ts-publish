<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources\Concerns\WrapsPost;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\Crm\Models\User as Post;

/** Takes its `$resource` `@var` from a trait, while this file imports another class as `Post`. */
final class TraitWrappedPostResource extends JsonResource
{
    use WrapsPost;

    /**
     * A column of the trait's `Post`, beside a key naming this file's own `Post`.
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
