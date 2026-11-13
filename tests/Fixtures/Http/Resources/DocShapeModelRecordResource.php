<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose `@return array<string, V>`, V a shape naming a model, types a key its body leaves `unknown`. */
final class DocShapeModelRecordResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, array{a: User}>
     */
    public function toArray(Request $request): array
    {
        return ['first' => json_decode('{"a":'.$this->content.'}')];
    }
}
