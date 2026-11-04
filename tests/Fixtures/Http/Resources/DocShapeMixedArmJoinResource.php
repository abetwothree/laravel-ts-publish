<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** A resource whose return branches join a fill holding an override and a post in one arm, its model in another. */
final class DocShapeMixedArmJoinResource extends JsonResource
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
        if ($this->id > 1) {
            return [...$this->first()];
        }

        return [
            'beside_null' => null,
            'beside_string' => (string) $this->content,
            'beside_post' => $this->post,
            ...$this->second(),
        ];
    }

    /**
     * The first branch's fills: the mixed arm's on three keys, a literal spelling `User` on the last.
     *
     * @return array{
     *     beside_null: array{a: Person, p: Post}|User,
     *     beside_string: array{a: Person, p: Post}|User,
     *     beside_post: array{a: Person, p: Post}|User,
     *     literal_first: array{kind: 'User'},
     * }
     */
    private function first(): array
    {
        return [
            'beside_null' => json_decode((string) $this->content),
            'beside_string' => json_decode((string) $this->content),
            'beside_post' => json_decode((string) $this->content),
            'literal_first' => json_decode((string) $this->content),
        ];
    }

    /**
     * The second branch's fill of the literal's key, the mixed arm's.
     *
     * @return array{literal_first: array{a: Person, p: Post}|User}
     */
    private function second(): array
    {
        return ['literal_first' => json_decode((string) $this->content)];
    }
}
