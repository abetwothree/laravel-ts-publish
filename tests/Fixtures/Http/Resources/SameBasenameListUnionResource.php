<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\User;
use Workbench\Crm\Models\User as CrmUser;

/** Publishes a helper whose `@return` unions two spaced int-keyed arrays of two models that share a name. */
final class SameBasenameListUnionResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return ['people' => $this->people()];
    }

    /**
     * The people the resource lists, from either model.
     *
     * @return array<int, User>|array<int, CrmUser>
     */
    private function people(): mixed
    {
        return [];
    }
}
