<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Comment;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** A helper read as a value whose body types nothing and whose `@return` shape names two models that share a name. */
final class DocShapePairResource extends JsonResource
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
        return ['pair' => $this->pair()];
    }

    /**
     * A shape naming two models that share a name.
     *
     * @return array{a: User, b: CrmUser}
     */
    private function pair(): array
    {
        return (array) json_decode('{}', true);
    }
}
