<?php

declare(strict_types=1);

namespace AbeTwoThree\LaravelTsPublish\Tests\Fixtures\DroppedArmsCorpus;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Workbench\App\Models\Post;
use Workbench\Crm\Models\User as CrmUser;

/**
 * A key for each drop site no workbench key reaches: a declared CRM `User` beside the author's, one name for two
 * classes (`unalike-fill`), and a `+=` default no rule types on a key a branch may skip (`plus-union`).
 *
 * @property-read Post $resource
 */
final class UnreachedSitesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['row' => [...$this->picked()], 'again' => [...$this->picked()], 'plus' => $this->defaulted()];
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

    /**
     * A `k` only a branch sets, then defaulted by a `+=` of a helper that cannot type it.
     */
    private function defaulted(): array
    {
        $d = ['id' => $this->resource->id];

        if ($this->resource->is_published) {
            $d['k'] = $this->resource->title;
        }

        $d += $this->defaults();

        return $d;
    }

    /**
     * Defaults whose `k` no rule types.
     */
    private function defaults(): array
    {
        return ['k' => cache('k')];
    }
}
