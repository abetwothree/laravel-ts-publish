<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** A resource whose spread helper joins a null and a post with a fill holding an override and a post in one arm. */
final class DocShapeDroppingMixedArmResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [...$this->outer()];
    }

    /**
     * A spread helper whose branches spread the fill, and set a null and a post.
     *
     * @return array<string, mixed>
     */
    private function outer(): array
    {
        if ($this->id > 1) {
            return [...$this->mixed()];
        }

        return ['k' => null, 'beside_post' => $this->post];
    }

    /**
     * The fill: an override and a post in one arm, the model spelled like the override in the other.
     *
     * @return array{k: array{a: Person, p: Post}|User, beside_post: array{a: Person, p: Post}|User}
     */
    private function mixed(): array
    {
        return ['k' => json_decode((string) $this->content), 'beside_post' => json_decode((string) $this->content)];
    }
}
