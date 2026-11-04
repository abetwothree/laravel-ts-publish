<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** A resource whose return branches join an override-beside-model fill with fills spelling `User` in a literal. */
final class DocShapeLiteralJoinResource extends JsonResource
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

        return [...$this->second()];
    }

    /**
     * The first branch's fills: a literal before the override-beside-model fill on two keys, after it on two.
     *
     * @return array{
     *     literal_first: array{kind: 'User'},
     *     slotted_first: list<Person>|User,
     *     disjoint_first: array{kind: 'User', p: Post},
     *     disjoint_last: list<Person>|User,
     * }
     */
    private function first(): array
    {
        return [
            'literal_first' => json_decode((string) $this->content),
            'slotted_first' => json_decode((string) $this->content),
            'disjoint_first' => json_decode((string) $this->content),
            'disjoint_last' => json_decode((string) $this->content),
        ];
    }

    /**
     * The second branch's fills, each key's other text.
     *
     * @return array{
     *     literal_first: list<Person>|User,
     *     slotted_first: array{kind: 'User'},
     *     disjoint_first: list<Person>|User,
     *     disjoint_last: array{kind: 'User', p: Post},
     * }
     */
    private function second(): array
    {
        return [
            'literal_first' => json_decode((string) $this->content),
            'slotted_first' => json_decode((string) $this->content),
            'disjoint_first' => json_decode((string) $this->content),
            'disjoint_last' => json_decode((string) $this->content),
        ];
    }
}
