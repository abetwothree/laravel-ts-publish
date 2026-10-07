<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Unit\Ast\Fixtures;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;

/**
 * Model::only() reads each key through getAttribute(), which returns an accessor the model does not append and loads
 * a relation under the name it was asked for.
 *
 * @mixin User
 */
final class UserOnlyRelationResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return $this->only(['id', 'initials', 'ownedTeams']);
    }
}
