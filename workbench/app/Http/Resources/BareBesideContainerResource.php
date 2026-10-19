<?php

declare(strict_types=1);

namespace Workbench\App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Collection;
use Workbench\App\Enums\Status;
use Workbench\App\Models\Comment;
use Workbench\App\Models\DocblockUnionFixture;
use Workbench\App\Models\User;

/**
 * A docblock union naming a class or an enum both inside a container and bare publishes both arms: a model's
 * `@property`, a property's `@var`, a helper's `@return` and a local's `@var`.
 *
 * @mixin DocblockUnionFixture
 */
final class BareBesideContainerResource extends JsonResource
{
    /** @var Collection<int, User>|User|null */
    public $crew;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var list<Comment>|Comment $either */
        $either = $this->resource->getRelationValue('either');

        return [
            'tags' => $this->resource->tags,
            'related_users' => $this->resource->related_users,
            'crew' => $this->crew,
            'lead' => $this->lead(),
            'statuses' => $this->statuses(),
            'either' => $either,
        ];
    }

    /**
     * @return User[]|User
     */
    private function lead(): mixed
    {
        return $this->resource->related_users;
    }

    /**
     * @return Status[]|Status
     */
    private function statuses(): mixed
    {
        return $this->resource->meta_info;
    }
}
