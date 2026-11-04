<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Workbench\App\Models\Post;
use Workbench\App\Models\User;

/** Names its model in a `$resource` `@var` whose first arm is a generic with a space inside it. */
final class SpacedGenericVarResource extends JsonResource
{
    /** @var Collection<int, User>|Post */
    public $resource;

    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['title' => $this->title];
    }
}
