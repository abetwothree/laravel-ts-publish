<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DroppedArmsCorpus;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A spread helper whose returns give `u` a declared CRM `User` and the post's author, a `User` of the app: one name
 * for two classes, so the join leaves the declared one out at the `unalike-fill` site, which no workbench key reaches.
 *
 * @property-read Post $resource
 */
final class UnalikeFillDropResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['row' => [...$this->picked()], 'again' => [...$this->picked()]];
    }

    /**
     * The author, read by a declaration on one path and by the relation on the other.
     */
    private function picked(): array
    {
        if ($this->resource->is_published) {
            $declared = $this->declared();

            return $declared;
        }

        return ['u' => $this->resource->author];
    }

    /**
     * A key whose body reads nothing, typed by its docblock alone.
     *
     * @return array{u: CrmUser}
     */
    private function declared(): array
    {
        return ['u' => app('author')];
    }
}
