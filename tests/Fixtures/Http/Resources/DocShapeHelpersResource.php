<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Helpers read as values whose bodies type nothing and whose `@return` shapes name models, at any depth. */
final class DocShapeHelpersResource extends JsonResource
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
        return [
            'shape' => $this->shape(),
            'obj' => $this->obj(),
            'inter' => $this->inter(),
            'tuple' => $this->tuple(),
            'nested' => $this->nested(),
        ];
    }

    /**
     * An array shape naming a model.
     *
     * @return array{owner: User, n: int}
     */
    private function shape(): array
    {
        return (array) json_decode('{}', true);
    }

    /**
     * An object shape naming a model.
     *
     * @return object{owner: User}
     */
    private function obj(): array
    {
        return (array) json_decode('{}', true);
    }

    /**
     * An intersection of two shapes, one naming a model.
     *
     * @return array{a: int}&array{owner: User}
     */
    private function inter(): array
    {
        return (array) json_decode('{}', true);
    }

    /**
     * A list shape naming a model at its first position.
     *
     * @return list{User, int}
     */
    private function tuple(): array
    {
        return (array) json_decode('[]', true);
    }

    /**
     * A shape nesting a shape that names a model and a list of another.
     *
     * @return array{meta: array{author: User, posts: list<Post>}}
     */
    private function nested(): array
    {
        return (array) json_decode('{}', true);
    }
}
