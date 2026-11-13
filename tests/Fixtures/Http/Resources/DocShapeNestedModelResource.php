<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose `@return` shape nests a model under a key its body leaves `unknown`. */
final class DocShapeNestedModelResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array{meta: array{author: User, n: int}}
     */
    public function toArray(Request $request): array
    {
        return ['meta' => json_decode('{"author":'.$this->content.',"n":1}')];
    }
}
