<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use AbeTwoThree\LaravelTsPublish\Attributes\TsCasts;
use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Workbench\App\Http\Resources\PostResource;
use Workbench\App\Models\Post;

/**
 * A docblock-filled payload signature beside a resource-typed same-pattern key broadcastWith()'s own cast retypes, so
 * only the publisher, once it fits the key's import channels to the cast, can join the fill.
 */
final class MethodCastFillBroadcastEvent implements ShouldBroadcast
{
    /** Takes the post its payload reads. */
    public function __construct(public Post $post) {}

    /** @return list<Channel> */
    public function broadcastOn(): array
    {
        return [new Channel('tags')];
    }

    /** @return array<string, mixed> */
    #[TsCasts(['main_tag' => 'number'])]
    public function broadcastWith(): array
    {
        return [...$this->docTags(), 'main_tag' => new PostResource($this->post)];
    }

    /**
     * `_tag` keys only the docblock types.
     *
     * @return array<string, string>
     */
    public function docTags(): array
    {
        $data = [];

        foreach (['east', 'west'] as $name) {
            $data["{$name}_tag"] = $this->opaque();
        }

        return $data;
    }

    /** Deliberately untyped. */
    protected function opaque()
    {
        return $this->post->getAttribute('title');
    }
}
