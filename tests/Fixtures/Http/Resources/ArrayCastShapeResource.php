<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Models\MetaComment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/** A resource whose `@return` shape types two `'array'`-cast columns and two decoded values its body leaves vague. */
final class ArrayCastShapeResource extends JsonResource
{
    /** @var MetaComment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array{meta: list<string>, tags: list<string>, owner: User, decoded: list<string>}
     */
    public function toArray(Request $request): array
    {
        return [
            'meta' => $this->meta,
            'tags' => $this->tags,
            'owner' => json_decode((string) $this->body),
            'decoded' => json_decode((string) $this->body),
        ];
    }
}
