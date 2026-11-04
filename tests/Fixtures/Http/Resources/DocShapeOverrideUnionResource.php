<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Ui\Person;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;

/** A resource whose own and spread helper's `@return` shapes spell an override (`Person` as `User`) beside a model. */
final class DocShapeOverrideUnionResource extends JsonResource
{
    /** @var Comment */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array{either: Person|User, meta: array{either: Person|User, n: int}}
     */
    public function toArray(Request $request): array
    {
        return [
            ...$this->spread(),
            'either' => json_decode((string) $this->content),
            'meta' => json_decode('{"either":'.$this->content.',"n":1}'),
        ];
    }

    /**
     * A spread helper whose own shape spells the override beside the model.
     *
     * @return array{spread: Person|User}
     */
    private function spread(): array
    {
        return ['spread' => json_decode((string) $this->content)];
    }
}
