<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Services\PostSummaryFormatter;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;

/** Wraps a formatter: forwarded calls pass its helpers, and the resource's own, a post they declare no type for. */
final class ForwardedArgumentResource extends JsonResource
{
    /** @var PostSummaryFormatter */
    public $resource;

    /**
     * Each forwarded call, passed the post.
     *
     * @return array<string, mixed>
     */
    public function forPost(Post $post): array
    {
        return [
            'forwarded' => $this->resource->row($post),
            'forwarded_static' => $this->resource::staticRow($post),
            'own_static' => $this::ownRow($post),
        ];
    }

    /**
     * @param  mixed  $post
     * @return array<string, mixed>
     */
    private static function ownRow($post): array
    {
        return ['own_title' => $post->title];
    }
}
